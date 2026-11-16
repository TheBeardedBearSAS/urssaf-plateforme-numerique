# ADR 0010 — Public integration events

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md) and
[ADR 0004](0004-submission-lifecycle.md).

## Context

With full parity, the package exposes versioned integration events through an outbox (reference `Contract\Event`).

## Decision

- `Contract\Event\IntegrationEvent` (abstract readonly, scalar properties `eventId`, `occurredAt`, `tenantId`,
  `aggregateId`, `externalReference`) and the v1 catalogue (`VERSION` 1 each):
  `urssaf.pn.submission.uploaded` (`urssafRef`), `urssaf.pn.submission.upload_rejected` (error codes),
  `urssaf.pn.submission.outcome_unknown`, `urssaf.pn.submission.not_received`, `urssaf.pn.submission.accepted` and
  `urssaf.pn.submission.rejected` (raw status and the report file references `fileId` / `name`),
  `urssaf.pn.declaration.payment_status_changed` (previous and new raw `paymentStatus`).
- Every event carries the kind, the declaration type (or null), the file name and the `urssafRef` when known; never
  `userId`, `userEmail` nor file contents.
- No event for `error` / `invalid_credentials`: the host gets the result synchronously.

## Consequences

- Documented in `docs/integration-events.md`; any payload change bumps `VERSION`.
