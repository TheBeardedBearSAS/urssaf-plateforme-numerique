# Implementation roadmap

Status: **implemented**. Every step below is done (lots 1 to 10). What remains is the run of the contract tests against
the real sandbox, then the removal of the open points of [api-reference.md](api-reference.md) section 5.

Specification: [api-reference.md](api-reference.md). Architecture, conventions and quality gates are the same as in
`thebeardedbear/urssaf-tiers-prestations` (hexagonal layers enforced by Deptrac, PSR-only runtime, Symfony and
Doctrine bridges, 100 % coverage and MSI). The decisions are in [docs/adr/](adr/README.md).

## Done

### Skeleton

- [x] `SpecVersion::CURRENT` (`1.0.0`), Docker tooling, quality gates (`make ci`), GitHub Actions CI.
- [x] Official wire fixtures in `tests/Fixtures/wire/` (uploads, news feed); hand-built ones are flagged as such.

### Domain (`src/Domain`)

- [x] Value objects: `PlatformId`, `UrssafReference`, `FileReference`, `FileName`, `ContentFingerprint`,
  `WithholdingPeriod`, `ReportedPeriod`, `DecimalNumber`, instants and API timestamps; enums `FileStatus`,
  `PaymentStatus`, `DeclarationType` with raw fallback.
- [x] Live read models of identifications, declarations (with statistics), report files and news items; `Page`,
  validated search criteria (`SubmissionSearch`, `PageRequest`, `SortOrder`).
- [x] `Submission` aggregate, `SubmissionState` and domain events ([workflow.md](workflow.md), ADR 0004).

### Application (`src/Application`)

- [x] Port `PlatformGateway` (the nine operations), `CredentialsProvider`, `TokenStore`, `PlatformResolver`,
  `SubmissionRepository`, `Outbox`, `FileSource`, `BinaryStream`, clock, ids, events; gateway exceptions.
- [x] Commands and queries with handlers: uploads, status synchronisation, refresh, reconciliation of unknown outcomes,
  manual resolution, outbox relay and purge, live queries, local queries and workflow overview; `UnitOfWork`,
  `WorkflowPolicy`. The optional blocking `WaitUntilProcessed` of the first plan was replaced by the polling use cases
  (ADR 0004).

### Infrastructure (`src/Infrastructure`)

- [x] `Environment` with the three targets (production, sandbox, test API) and the host override (ADR 0001).
- [x] OAuth2 client credentials with a cached token (PSR-6 / PSR-16 / in-memory), multipart uploads, streamed
  downloads, status mapping, `Retry-After`, `Accept-Language`, `UCN-*` headers, error payload parser (ADR 0002,
  ADR 0003).
- [x] Wire mappers, in-memory and PSR adapters, optional rate limiter, local and in-memory file sources.

### Bridges, standalone and testing

- [x] Symfony bundle `UrssafPlateformeNumeriqueBundle`: configuration, `urssaf:pn:*` commands, Messenger, Scheduler,
  locks, translations, Flex recipe ([symfony.md](symfony.md), ADR 0012).
- [x] Doctrine ORM 3 / DBAL 4 bridge with a submission repository, outbox and statistics
  ([doctrine.md](doctrine.md), ADR 0011).
- [x] Standalone builder ([standalone.md](standalone.md)) and in-memory simulated platform
  ([testing.md](testing.md)).
- [x] Public integration events `urssaf.pn.*` ([integration-events.md](integration-events.md), ADR 0010).

### Tests and documentation

- [x] Unit and functional tests at 100 % coverage and mutation score, PHPBench benchmarks.
- [x] Opt-in contract tests (`tests/Contract`, `make test-contract`, manual CI job `contract`).
- [x] README, API reference, workflow, roadmap, ADRs.

## Remaining

1. Run the contract tests against the real sandbox (`api-edi`) and the test API, with real credentials and platform
   id (see the README). They cover the token host of the sandbox, the pagination headers, the 404 of an unknown reference,
   the news feed on both targets (absent on the test API) and, with `URSSAF_SANDBOX_UPLOAD=1`, the echo of the uploaded
   name used by the reconciliation. The multipart `name` / `data` semantics, the error codes, the time zone of
   `lastUpdateDate`, the defaults of `page` / `size` and the endpoint behind the orphan `UserIdentification*` schemas have
   no contract test: they need URSSAF's answer or a manual check during that run.
2. Remove the open points from [api-reference.md](api-reference.md) section 5 as they are settled, and adjust the
   assumptions recorded in the ADRs (0001, 0002, 0003, 0006).
3. First release (version 0.x), then publication of the Flex recipe in symfony/recipes-contrib.
