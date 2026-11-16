# ADR 0001 — Three targets and the news feed on the test API

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The reference `Environment` has two cases (sandbox, production) and one base path. This API has three targets with the
same resources (api-reference §1, §4.1): Échanges réels production (`https://api.urssaf.fr`,
`/api-odspn-be-platforms/v1`, scope `ods.pn.platforms`), Échanges réels sandbox (`https://api-edi.urssaf.fr`, same path
and scope) and the Bac à sable test API (`https://api.urssaf.fr`, `/api-odspn-be-platforms-sandbox/v1`, scope
`ods.pn.platforms.sandbox`). The news feed is not published on the test API (§2.9, §4.2).

## Decision

- `Infrastructure\Urssaf\Environment` has three cases: `Production` (`production`), `Sandbox` (`sandbox`) and
  `TestApi` (`test_api`), each exposing `baseUri()`, `basePath()` and `scope()` from the `UrssafEndpoints` constants,
  and `supportsNewsFeed()`.
- `ApiConfiguration` keeps the reference host override (`baseUriOverride`) for every target; the base path and the
  scope follow the target and are not overridable.
- The token is requested from `<host>/api/oauth/v1/token` of the target. The sandbox JSON contract declares the
  production `tokenUrl`; which host issues `api-edi` tokens stays an open point (§5), settled by the contract tests;
  the host override covers both URLs.
- The news feed on `TestApi` throws `Application\Port\Urssaf\OperationNotSupported` before any HTTP call. The contract
  tests check that the real test API answers 404 there.
- `Application\Port\Operation` is an enum of the nine operations, backed by their `operationId` (verbatim from the
  contract); it names the operation in logs, exceptions and locks.

## Consequences

- One package and one configuration value select the target; credentials stay per tenant ([ADR 0007](0007-tenant-model.md)).
- The token cache key includes the target and the scope ([ADR 0002](0002-http-client-adaptations.md)).

## Amendment (2026-11-16, lead-accepted, lot 8)

- The token host of the sandbox and the behaviour of the test API (404 on the news feed) is checked only when the opt-in contract tests are run against the sandbox (`make test-contract`, or the manual
  `contract` job). Until such a run, the point stays open in [docs/api-reference.md](../api-reference.md) §5.
