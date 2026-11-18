# ADR 0000 — Reference architecture inherited from urssaf-tiers-prestations

Status: Accepted (2026-11-16). Reference: thebeardedbear/urssaf-tiers-prestations (HEAD 67b712a): README
"Architecture", docs/api-reference.md §4, docs/workflow.md, docs/symfony.md, docs/doctrine.md, docs/testing.md,
docs/standalone.md, docs/integration-events.md, phpstan.neon.dist, mago.toml, rector.php. Sibling precedents:
urssaf-tierce-declaration-cesu (stateful) and urssaf-attestations-verification-code-securite (stateless).

## Context

Every URSSAF package shares one architecture, so integrators and maintainers move between them freely. This ADR lists
what this package inherits, adapts or leaves out. Any other deviation needs its own ADR (Context / Decision /
Consequences), accepted by the lead before the code that implements it.

## Inherited as is

1. **Layers (Deptrac).** Domain and Contract depend on nothing; Application → Domain, Contract; Infrastructure →
   Application, Domain, Contract, Psr; Testing → Domain, Contract, Application, Infrastructure; DoctrineBridge →
   Domain, Contract, Application, Infrastructure, Doctrine, Psr; Standalone → Domain, Contract, Application,
   Infrastructure, DoctrineBridge, Doctrine, Psr (no Symfony); SymfonyBridge → everything above + Testing, Symfony,
   Doctrine, Psr. Tests are not analysed.
2. **PSR-only runtime.** `php >= 8.2`, `ext-*`, `psr/*` interfaces. Symfony, Doctrine and HTTP clients are
   require-dev + suggest, with the reference conflict floors (`symfony/scheduler <6.4.3` included).
3. **Value objects.** `final readonly`, private constructor, named constructors (`of`, `fromString`), stable violation
   codes `<subject>.<reason>`, no raw value in messages; `Credentials` not serializable, secrets
   `#[SensitiveParameter]`, `__debugInfo` masking of secrets and personal data.
4. **Validation.** Notification pattern (`Violation`, `ValidationResult`, `Validator`): every violation of an input is
   collected at once and thrown as `ValidationFailed`.
5. **Errors.** `UrssafException` and `RetryableException` markers; abstract `DomainException` (`violationCode()`,
   `params()`, `toViolation()`) with `InvalidValue`, `InvariantViolation`, `InvalidStateTransition`, `RecordNotFound`,
   `ValidationFailed`; gateway exceptions `UrssafRequestRejected` (400), `UrssafAuthenticationFailed`,
   `UrssafUnavailable` (429 / 5xx / transport, `mayHaveReachedUrssaf()`), `UnexpectedUrssafResponse`.
   `ErrorPayloadParser` is lenient on error bodies.
6. **Wire.** `Payload` is the single reader of untyped data; `JsonDecoder` uses `JSON_THROW_ON_ERROR |
   JSON_BIGINT_AS_STRING`; a payload that does not match the contract throws `UnexpectedUrssafResponse`; values of a
   closed list are kept raw next to a nullable enum (`fromCode(): ?self`), so an unknown value never breaks a read.
7. **HTTP.** OAuth2 client credentials, `TokenStore` (PSR-6, PSR-16, in-memory; `CacheKey`), 60 s safety margin, one
   replay after a 401, `RetryAfter` (delta-seconds only), host overridable, only method / path / status / duration
   logged, never a token, a secret, a body or a query string.
8. **Application.** One `final readonly` handler per use case (`__invoke`), queries separated from commands,
   `UnitOfWork` (domain events published after commit, integration events appended to the outbox inside the
   transaction, `FailureReporter`), atomic claim before any URSSAF write, attempts counter, `invalid_credentials`
   state, `WorkflowPolicy` holding every tunable.
9. **Events.** Domain events recorded by aggregates; public integration events in `Contract\Event` (scalar, `TYPE` /
   `VERSION`, `IntegrationEventCodec`), relayed from the outbox at least once (Messenger, event dispatcher or custom
   sink).
10. **Multi-tenant.** `TenantId` on every aggregate and row, `CredentialsProvider::forTenant()`, unique constraints and
    locks per tenant.
11. **Bridges.** Symfony: `AbstractBundle`, `ServiceIds`, `BundleConfiguration` with build-time rules, registrars,
    console commands with `--tenant` / `--limit` / `--dry-run`, scalar Messenger messages with recoverable /
    unrecoverable mapping, non-blocking `OperationLocks`, Scheduler provider, installation checks, en / fr
    translations kept in parity, Flex recipe tested by `FlexRecipeTest`. Doctrine: XML mapping (`SimplifiedXmlDriver`),
    custom DBAL types, DBAL outbox, `DoctrineTransactionManager`, dedicated entity manager recommended, no migration
    shipped. Standalone builder. Testing simulator (`gateway.simulated`, refused in production).
12. **Tests.** Unit / Functional / Contract suites; hand-written doubles (`Fake*`, `InMemory*`, `Recording*`, `Fixed*`
    / `Stepping*`) rather than mocks; shared repository contract traits run against the in-memory and the Doctrine
    implementations; the Contract suite is skipped without `URSSAF_SANDBOX_CLIENT_ID` / `URSSAF_SANDBOX_CLIENT_SECRET`
    and runs only in the manual CI job; PHPBench benchmarks; 100 % lines / methods / classes, MSI 100 %, PHPStan max,
    Mago, Rector, php-cs-fixer, Deptrac, composer checks, hadolint, guard.
13. **Quality exceptions.** Only those justified in the reference configuration (container parameters, `noExtends`
    for framework extension points / `DomainException` / `IntegrationEvent`, default and nullable parameters, nullable
    finder returns, non-final Doctrine entities, static assertions in tests, wire DTO constructor threshold 12, the
    Rector skips, no `mago fmt`).

## Adapted (each with its own ADR)

- Three targets (real production, real sandbox `api-edi`, test API "bac à sable"); news feed refused locally on the
  test API — [ADR 0001](0001-targets.md).
- HTTP client: multipart uploads, binary downloads, `Accept-Language`, `UCN-*` pagination headers, no
  `x-request-id` / `recouv.application.id` by default, status mapping without published error codes, no rate limit by
  default — [ADR 0002](0002-http-client-adaptations.md).
- An upload whose outcome is unknown is reconciled through the search operations, never resent blindly —
  [ADR 0003](0003-unknown-upload-outcome.md).
- Local lifecycle of submissions (start, claim, re-entry, polling of `status` and `paymentStatus`) —
  [ADR 0004](0004-submission-lifecycle.md).
- Files: streamed, never generated, decoded nor stored — [ADR 0005](0005-files.md).
- Numbers, periods and dates without published unit, format or time zone — [ADR 0006](0006-numbers-and-dates.md).
- Tenant = one credential set and one `platformId` — [ADR 0007](0007-tenant-model.md).
- Local guard "one `DECLARATION` and one `REGULARISATION` in process", with an opt-out —
  [ADR 0008](0008-concurrent-declaration-guard.md).
- Personal data (`userId`, `userEmail`, report files) — [ADR 0009](0009-personal-data.md).
- Integration event catalogue `urssaf.pn.*` — [ADR 0010](0010-integration-events.md).
- Doctrine bridge — [ADR 0011](0011-doctrine-bridge.md); Symfony bridge — [ADR 0012](0012-symfony-bridge.md).
  The Doctrine bridge is delivered before the Symfony bundle, so that the bundle supports the `doctrine` driver from
  its first version.
- PHPStan always runs on PHP 8.2 — [ADR 0013](0013-static-analysis-on-minimum-php.md).

## Not applicable

- Batching by 10 and per-operation quotas: every call is unitary and no quota is published (api-reference §1.1).
- Error-code catalogue (`ApiErrorCode`) and `ErrorClassifier`: no error code is published (§1.3). Errors are classified
  by HTTP status only and the codes are kept raw.
- Money in integer cents: the unit and currency of `statistics` are not published ([ADR 0006](0006-numbers-and-dates.md)).
- ASCII transliteration, customers, pairing, mandates, billing ids: no equivalent in this API.
- Payload storage and asynchronous submission through Messenger: the file content is never stored
  ([ADR 0005](0005-files.md)), so uploads are synchronous.
- Orphan schemas of the Bac à sable (§3.6): no endpoint is published.

## Process

New decisions and deviations from the reference get an ADR `docs/adr/NNNN-<title>.md` (Context / Decision /
Consequences), accepted by the lead before the code that implements them. Amendments are appended to the ADR they
change, dated and marked lead-accepted.

## Amendment (2026-11-18, lead-accepted)

- Infection `timeout` is 180 s instead of the reference 30 s, and `make infection` and the CI job fail when a mutant
  is *skipped*: Infection never runs a mutant whose covering tests exceed the limit in total, while MSI still reads
  100 %. The change makes the gate stricter (justified in `infection.json5`).
