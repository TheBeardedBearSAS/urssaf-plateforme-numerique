# ADR 0009 — Personal data

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

`userId` and `userEmail` (the uploader) are personal data (§4.12). Report files may contain personal data of the
platform's users. Error descriptions may echo submitted values.

## Decision

- `userId` and `userEmail` are exposed only on the live read models (`IdentificationSubmission`,
  `DeclarationSubmission`), masked in `__debugInfo`; they are never persisted, logged, put in an exception message or in
  an integration event.
- File contents and report files are never stored nor logged ([ADR 0005](0005-files.md)). The stored fingerprint is a
  one-way SHA-256 digest.
- URSSAF error descriptions are kept on the exceptions and in the `upload_rejected` record (codes and messages only,
  needed by the operator) but never logged; console commands print codes and messages only.
- The `search` keyword is never logged (only method and path are).

## Consequences

- The Doctrine bridge has no column for `userId` / `userEmail` (asserted by a test).
