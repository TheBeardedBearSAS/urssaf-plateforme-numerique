# ADR 0008 — Guard: one declaration of each type in process

Status: Accepted (2026-11-16). Relates to [ADR 0004](0004-submission-lifecycle.md).

## Context

"Only one `DECLARATION` and one `REGULARISATION` can be in process at a time" per platform; a new file of the same type
is accepted (`200`) then rejected asynchronously with a specific anomaly (§1.2). For identifications the contract only
says one file per period "should" be submitted.

## Decision

- `UploadDeclaration` refuses locally, with `InvariantViolation` `submission.declaration_in_process`, an upload of a
  type while another submission of the same tenant and type is `uploading`, `in_process` or `outcome_unknown`.
- Opt-out: `WorkflowPolicy::withoutConcurrentDeclarationGuard()` (bundle `workflow.concurrent_declaration_guard:
  false`), for hosts that track the processing elsewhere or when the local store is incomplete.
- No guard on identifications ("should" is not a rule).
- The check runs inside the claim transaction; the Symfony bridge also serialises uploads per tenant and type with a
  non-blocking lock. Without a lock, two processes may still race; the asynchronous rejection then applies.

## Consequences

- A predictable asynchronous rejection is turned into an immediate, explained refusal.

## Amendment (2026-11-16, lead-accepted, lot 8)

- Only the console upload commands (`urssaf:pn:identification:upload`, `urssaf:pn:declaration:upload`) take the lock per
  tenant and type. A handler called directly (standalone, controller) takes none: the in-transaction check above is
  then the only local guard.
