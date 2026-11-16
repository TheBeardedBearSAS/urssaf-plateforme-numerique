# ADR 0003 — Unknown upload outcome

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md) and
[ADR 0004](0004-submission-lifecycle.md).

## Context

The uploads (§2.1, §2.5) have no idempotency key. A blind resend after a lost answer may create a second submission:
for a `DECLARATION` the second one is rejected asynchronously (one in process at a time, §1.2), for an identification
it may be rejected because only one file per period "should" be submitted. Unlike CESU, the API has search operations
(§2.3, §2.7) that list every submission of the platform, with its file name (`identificationFileName`,
`declarationFileName`) and `urssafRef`.

## Decision

- Safe to retry (`RetryableException`): the token call, every read (get, search, download, news feed), and the uploads
  when the call provably did not reach URSSAF: 429, a local rate-limit refusal, a token failure, the replay after a
  401.
- Never resent automatically: the two uploads. A transport error, any 5xx, or a 2xx answer that cannot be read
  (invalid JSON, missing or empty `urssafRef`) throws `Application\Port\Urssaf\OutcomeUnknown`
  (`mayHaveReachedUrssaf()` is true). 400 is a rejection (nothing recorded by URSSAF); 1xx, 3xx and other 4xx are
  `UnexpectedUrssafResponse` (not processed).
- The local submission moves to `outcome_unknown`. A submission left `uploading` longer than
  `WorkflowPolicy::staleUploadAfter()` (process crash between claim and answer) is moved to `outcome_unknown` too.
- `ReconcileUnknownSubmissions` searches the matching operation (identifications, or declarations filtered by
  `type`), sorted `lastUpdateDate:DESC` (a sort key shown by the contract examples), `size` 500, up to
  `WorkflowPolicy::reconciliationMaxPages()` pages, and adopts the only result whose file name equals the uploaded name
  and whose `urssafRef` is not already known locally for the tenant: the submission moves to `in_process` with that
  reference and is then polled normally. That URSSAF echoes the uploaded `name` verbatim in the file name field is
  **not published** (§5) and is checked by the contract tests.
- Zero or several candidates leave the submission `outcome_unknown`; past `WorkflowPolicy::manualReviewAfter()` it is
  reported as needing manual review (`GetWorkflowOverview::needingAttention()`). An operator records the result with
  `ResolveSubmissionOutcome`: received (with its `urssafRef`) → `in_process`, or not received → `not_received`, after
  which the host may upload the same file again ([ADR 0004](0004-submission-lifecycle.md)).
- Messenger: `OutcomeUnknown` and every non-retryable package exception are unrecoverable; `RetryableException` is
  recoverable with its delay.

## Consequences

- The package never produces a duplicate submission by itself.
- Reconciliation reads at most `reconciliationMaxPages × 500` items per submission and run; the search cost is bounded.
