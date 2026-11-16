# ADR 0004 — Local lifecycle of submissions

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md),
[ADR 0003](0003-unknown-upload-outcome.md), [ADR 0005](0005-files.md) and [ADR 0008](0008-concurrent-declaration-guard.md).

## Context

Uploads are asynchronous (§1.2): a `200` only returns an `urssafRef`; the processing `status` (`IN_PROCESS`,
`ACCEPTED`, `REJECTED`, §3.1) and, for declarations, the independent `paymentStatus` (§3.2) must be polled. The
reference drives its records through explicit state machines (pending → submitting claim → …, polling windows,
reconciliation). Here the file content is never stored ([ADR 0005](0005-files.md)), so there can be no pending queue
of files waiting to be sent.

## Decision

- **Aggregate.** `Domain\Submission\Submission`, per tenant: local `SubmissionId`, host `ExternalReference` (unique
  per tenant), `PlatformId`, `SubmissionKind` (`identification` | `declaration`), `DeclarationType` (declarations
  only), file name, `ContentFingerprint`, `SubmissionState`, attempts, `UrssafReference` once known, the last
  `SubmissionSnapshot` read from URSSAF (raw `status` and `paymentStatus`, periods, report file references, counters,
  `lastUpdateDate`; never `userId` / `userEmail`, [ADR 0009](0009-personal-data.md)), the time of the last poll, and
  the creation and update instants.
- **Fingerprint.** SHA-256 of kind, declaration type, file name and the SHA-256 of the content, computed in the
  Application layer by streaming the `FileSource` once before the upload.
- **States** (`SubmissionState`): `uploading` (claimed, the call is in flight) → `in_process` (URSSAF returned an
  `urssafRef`) → `accepted` | `rejected` (from the polled `status`); `upload_rejected` (400), `error` (the call
  provably did not reach URSSAF), `invalid_credentials` (401 after the replay, 403, token refused), `outcome_unknown`
  ([ADR 0003](0003-unknown-upload-outcome.md)), `not_received` (operator decision). Invalid transitions throw
  `InvalidStateTransition`.
- **Synchronous upload.** `UploadIdentification` / `UploadDeclaration` create (or reclaim) the record in `uploading`
  in one transaction, then call URSSAF, then record the outcome in a second transaction. There is no asynchronous
  upload.
- **Re-entry** (same tenant and external reference):

  | Existing record | Same fingerprint | Different fingerprint |
  |---|---|---|
  | none | created, uploaded | — |
  | `uploading` | `not_claimed` (another process is sending it) | `SubmissionAlreadyRecorded` |
  | `in_process`, `accepted`, `rejected`, `outcome_unknown` | replayed: the record is returned, nothing is sent | `SubmissionAlreadyRecorded` |
  | `error`, `invalid_credentials`, `upload_rejected`, `not_received` | reclaimed (atomic claim, attempts + 1) and uploaded, until `WorkflowPolicy::maxUploadAttempts()` (default 5), then `exhausted` | `SubmissionAlreadyRecorded` |

- **Results.** The upload handlers return an `UploadReport` (`uploaded`, `replayed`, `not_claimed`, `rejected` with the
  `ApiErrors`, `invalid_credentials`, `outcome_unknown`, `exhausted`). A retryable failure moves the record to `error`
  and is rethrown as `RetryableUrssafFailure` (with the `Retry-After` delay when known), as the reference does.
- **Polling.** `SyncSubmissionStatuses` (per tenant, `limit`) refreshes, least recently polled first, the submissions
  that are `in_process`, and the declarations whose `paymentStatus` is not settled. A declaration is settled when it is
  `ACCEPTED` and `PROCESSED` (the "full flow complete" of §3.2); every other combination may still change (the
  `INTERLOCUTION` and `DECLARED` / `PROCESSING` round trips of §3.2 included). Identifications are not polled once
  `accepted` or `rejected`. A submission is not polled again before `WorkflowPolicy::statusPollInterval()` (while
  `in_process`) or `paymentPollInterval()` (afterwards), nor after `maxPollingAge()` since its upload. These defaults
  (10 minutes, 6 hours, 1 year) are package choices, not URSSAF rules, and are configurable.
- **Unknown values.** An unknown `status` keeps the record `in_process` (still polled) with the raw value stored; an
  unknown `paymentStatus` is stored raw and keeps the declaration unsettled.
- **Live reads.** `GetIdentification`, `GetDeclaration`, the searches, the downloads and the news feed are queries
  that call URSSAF directly and return domain read models; they never change local records. `RefreshSubmission`
  refreshes one local record.

## Consequences

- Unique constraint `(tenant, external_reference)`; the claim is one conditional update.
- A host that loses the file before an `error` cannot retry from the package; it keeps its own copy.
- No `RequeueAfterInvalidCredentials` command: re-entry by the host covers it, since the content is not stored.

## Amendment (2026-11-16, lead-accepted, lots 5-8)

- A poll that URSSAF does not answer with a snapshot calls `Submission::recordPollWithoutAnswer()` (through
  `SubmissionPoller`, in its own transaction). This covers a 404 (`UrssafResourceNotFound`, report item `not_found`)
  and a 400 (`UrssafRequestRejected`, item `failed`) during `SyncSubmissionStatuses`. Only the last poll instant moves:
  no event is recorded, and the state, the snapshot and the update instant are kept. Since the submissions are polled
  least recently polled first, the next run polls the others before it, and one unanswerable submission cannot starve
  them. It is still polled at its interval until `maxPollingAge()`. `RefreshSubmission` does not do this: its read
  failures propagate.
- The time of the last poll is the Domain value object `LastPoll` (`polledAt`, plus the raw `status` and
  `paymentStatus` of the last answer, which an unanswered poll keeps). `Submission::needsPolling()` reads whether the
  payment is settled from it. It is mapped as real columns by the Doctrine bridge
  ([ADR 0011 amendment](0011-doctrine-bridge.md#amendment-2026-11-16-lead-accepted-lot-7)).
- `maxPollingAge()` is counted from the creation of the record, that is the first claim. A reclaim does not reset it.
