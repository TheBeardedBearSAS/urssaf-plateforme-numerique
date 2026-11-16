# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-11-16

First release.

### Upgrading

- Driver `doctrine`: generate and run the migration for the `urssaf_pn_*` tables (`doctrine:migrations:diff
  --em=urssaf_pn`, `doctrine:migrations:migrate`); the package ships no migration. `urssaf:pn:check` reports the
  missing tables.

### Added

- Domain model: value objects (tenant, platform id, external reference, URSSAF references, file names and
  fingerprints, withholding periods, decimal numbers, instants and API timestamps), the `FileStatus`, `PaymentStatus`
  and `DeclarationType` enums with raw fallback, the live read models of identifications, declarations and news
  items, pagination, validated search criteria, and the `Submission` aggregate with its local lifecycle and domain
  events (ADR 0004). Runtime requirements `ext-filter` and `ext-mbstring`.
- Application ports (`PlatformGateway` for the nine operations, credentials, token store, platform resolver,
  submission repository, outbox, events, clock, ids, file source, binary download stream) and the gateway
  exceptions (`UrssafRequestRejected`, `UrssafAuthenticationFailed`, `UrssafUnavailable`, `UrssafResourceNotFound`,
  `OutcomeUnknown`, `OperationNotSupported`).
- Public integration events `urssaf.pn.*` (`Contract\Event`, `docs/integration-events.md`, ADR 0010).
- Wire mappers for identifications, declarations, the news feed, upload answers, error payloads, `UCN-*`
  pagination headers and query strings, with hand-built fixtures flagged as such (`tests/Fixtures/wire/README.md`).
- HTTP gateway (`UrssafHttpGateway`, `UrssafGatewayFactory`): OAuth2 client credentials with a cached token
  (PSR-6 / PSR-16 / in-memory stores), the three targets (production, sandbox, test API), multipart uploads,
  streamed downloads, `Accept-Language`, `Retry-After`, the status mapping of ADR 0002 and the unknown upload outcome
  of ADR 0003. Adapters: clocks, UUIDs, PSR-14 event publisher and sink, in-memory outbox, repository, credentials
  and platform resolver, PSR-3 failure reporter, local and in-memory file sources, optional sliding-window rate
  limiter. Runtime requirements: `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/log`,
  `psr/cache`, `psr/simple-cache`, `psr/event-dispatcher`, `psr/clock`, `ext-json`.
- Application use cases: `UploadIdentification` / `UploadDeclaration` (fingerprint, idempotent re-entry, declaration
  guard, unknown outcome), `SyncSubmissionStatuses`, `RefreshSubmission`, `ReconcileUnknownSubmissions` (search by
  file name, ADR 0003), `ResolveSubmissionOutcome`, outbox relay and purge; live queries (get, search, download,
  news feed) and local queries (submission, list, workflow overview); `UnitOfWork`, `WorkflowPolicy`.
- Standalone builder and facade (`UrssafPlateformeNumeriqueBuilder`, `docs/standalone.md`) to use the package
  without a framework; in-memory URSSAF platform for host tests (`Testing\SimulatedPlatformGateway`,
  `docs/testing.md`); PHPBench benchmarks of the search validation, the query and multipart encoding and the decoding
  of the official payloads and of full 500-item search pages (`make bench`).
- Doctrine ORM 3 / DBAL 4 bridge (`Bridge\Doctrine`, `docs/doctrine.md`): XML mapping, `urssaf_pn_*` types and
  tables, submission repository with an atomic claim and optimistic concurrency, DBAL outbox, workflow statistics,
  transaction manager, and `UrssafPlateformeNumeriqueBuilder::withDoctrine()`. No personal data column (ADR 0009,
  ADR 0011).
- Symfony bundle `UrssafPlateformeNumeriqueBundle` (alias `urssaf_plateforme_numerique`, `docs/symfony.md`):
  configuration with build-time rules, `urssaf:pn:*` console commands (uploads, sync, reconcile, resolve, show,
  report download, news, status, outbox, check), Messenger messages, Scheduler schedule, non-blocking locks, en / fr
  translations, simulated gateway for tests, and a Flex recipe (`recipe/`). Dev dependency `symfony/scheduler`
  (conflict floor `<6.4.3`, as the reference).
- Opt-in contract tests against the URSSAF sandbox and the test API (`make test-contract`, manual CI job
  `contract`), skipped without `URSSAF_SANDBOX_CLIENT_ID` / `URSSAF_SANDBOX_CLIENT_SECRET`.
- Architecture decision records (`docs/adr/`): the reference architecture inherited from
  `thebeardedbear/urssaf-tiers-prestations` (ADR 0000) and the decisions specific to this API (ADR 0001 to 0013).
- Project skeleton: Docker tooling, quality gates (`make ci`) and GitHub Actions CI, identical to the other URSSAF
  packages.
- Specification: official OpenAPI contracts (`docs/openapi/`), official documentation (links in `docs/api-reference.md`, local copy in the git-ignored `docs/reference/`), technical
  reference (`docs/api-reference.md`) and implementation roadmap (`docs/roadmap.md`).
- Signed releases (workflow `release.yml`): each GitHub release carries the distribution archive, its `SHA256SUMS`
  and a SLSA build provenance attestation (`*.intoto.jsonl`, Sigstore); see *Verifying a release* in SECURITY.md.
- CodeQL analysis of the GitHub Actions workflows (workflow `codeql.yml`) on every pull request and push to `main`.

### Changed

- README, API reference and roadmap: the package is implemented.
- CI and tooling, aligned with the sibling packages: CI job `flex-endpoint` (builds the Flex endpoint of `recipe/`),
  `phpunit.xml.dist` runs `unit,functional` by default (the contract suite only through `make test-contract`), the
  SonarQube Cloud exception for the test classes whose methods come from the shared contract traits, and the
  `symfony/cache` suggestion.
- CI: verifiable quality proofs (Codecov, Stryker Dashboard, SonarQube Cloud, OpenSSF Scorecard; active once the
  repository is public), every GitHub Action pinned to a commit SHA and kept up to date by Dependabot, hadolint on the
  Dockerfile (`make hadolint`, part of `make ci`) with a non-root development image, and benchmarks with PHPBench
  (`make bench`, CI job `benchmark`, isolated in `tools/phpbench`).
- CI: the SonarQube Cloud analysis also requires the repository variable `SONARCLOUD=enabled`, set once the
  project is imported on SonarQube Cloud.

- README: maintainer (The Bearded Bear), the other URSSAF packages and professional support; composer
  `authors` now points to https://the-bearded-bear.com.

- Deptrac declares the `Contract`, `Testing` and `Standalone` layers of the reference architecture; PHPStan always
  runs on PHP 8.2, the supported floor (`make ci PHP_VERSION=8.4` no longer fails on typed constants, ADR 0013);
  `CONTRIBUTING.md` states that tests are mandatory.

- The package is open source under the MIT license (previously proprietary). Added `CONTRIBUTING.md` and
  `SECURITY.md`, and a notice in the README stating that the package is unofficial and not affiliated with URSSAF.
