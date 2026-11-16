# ADR 0011 — Doctrine bridge

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md),
[ADR 0004](0004-submission-lifecycle.md) and [ADR 0009](0009-personal-data.md).

## Context

The reference ships a Doctrine ORM 3 / DBAL 4 bridge with XML mapping, custom types, a DBAL outbox and a progress table.

## Decision

- Reference structure: XML mapping, custom DBAL types `urssaf_pn_*`, tables `urssaf_pn_submissions` and
  `urssaf_pn_outbox`, `DoctrineTransactionManager`, `DoctrineOutbox`, `DoctrineWorkflowStatistics`, dedicated entity
  manager recommended, no migration shipped. No name collides with the other URSSAF packages.
- No progress table: state, kind, declaration type, raw status and payment status, `urssaf_ref`, last poll and
  upload instants are real columns, indexed for polling `(tenant, state, last_polled_at)`; unique
  `(tenant, external_reference)`.
- The snapshot (periods, report references, counters) is a JSON column (no query filters on it).
- Optimistic concurrency through a native Doctrine version field (`private int $version`, CESU ADR 0014), translated
  into `InvariantViolation` `submission.concurrent_modification`; the claim is one conditional `UPDATE` bumping the
  version.
- `Submission` is not final (Doctrine proxies; `@final` by convention), declared in `phpstan.neon.dist` as in the
  reference.

## Consequences

- Repository behaviour is proven by contract traits shared with the in-memory repository.
