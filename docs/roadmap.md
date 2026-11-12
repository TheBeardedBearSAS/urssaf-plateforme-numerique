# Implementation roadmap

Specification: [api-reference.md](api-reference.md). Architecture, conventions and quality gates are the same as in
`thebeardedbear/urssaf-tiers-prestations` (hexagonal layers enforced by Deptrac, PSR-only runtime, Symfony and
Doctrine bridges, 100 % coverage and MSI).

## Already in place (skeleton)

- `SpecVersion::CURRENT` (`1.0.0`).
- `Infrastructure\Urssaf\Environment` (Sandbox / Production base URIs) and `Infrastructure\Urssaf\UrssafEndpoints`
  (token path, scope `ods.pn.platforms`, base path `/api-odspn-be-platforms/v1`, and the `TEST_API_*` constants:
  scope `ods.pn.platforms.sandbox`, base path `/api-odspn-be-platforms-sandbox/v1`).
- Official wire fixtures in `tests/Fixtures/wire/` (uploads, news feed).

## Domain (`src/Domain`)

- `Platform\PlatformId` (value object: non-empty string; stricter format only once confirmed, see api-reference §5).
- `Submission\UrssafReference`, `Submission\FileReference` (`fileId` + `name`), `Submission\WithholdingPeriod`
  (`YYYY-MM`), `Submission\FileStatus` (enum §3.1), `Submission\PaymentStatus` (enum §3.2), `Submission\DeclarationType`
  (enum §3.3); enums keep unknown values through a raw fallback.
- `Identification\IdentificationSubmission` (+ `IdentificationDetails`), `Declaration\DeclarationSubmission`
  (+ `DeclarationStatistics`), `Report\ReportFiles` (global / detailed / users anomalies / affiliated users).
- `NewsFeed\NewsItem` (id, text, date).
- `Pagination\Page` (items + total pages / count / current page / per page).
- `Domain\Exception\*`.

## Application (`src/Application`)

- Port `Port\Urssaf\PlatformGateway`: `uploadIdentification`, `getIdentification`, `searchIdentifications`,
  `downloadIdentificationFile`, `uploadDeclaration`, `getDeclaration`, `searchDeclarations`, `downloadDeclarationFile`,
  `newsFeed` (the last one unsupported on the test API, api-reference §4.2).
- Ports `CredentialsProvider`, `TokenStore` (same contracts as Tiers de prestations), `FileSource` (name + stream).
- Commands / queries with handlers: `UploadIdentification`, `UploadDeclaration`, `GetIdentification`, `GetDeclaration`,
  `SearchIdentifications`, `SearchDeclarations`, `DownloadReportFile`, `GetNewsFeed`; optional `WaitUntilProcessed`
  (polling until `status != IN_PROCESS`, api-reference §4.5).

## Infrastructure (`src/Infrastructure`)

- `Urssaf\Environment`: **must model three targets**: real production (`api.urssaf.fr` + `/api-odspn-be-platforms/v1`,
  scope `ods.pn.platforms`), real sandbox (`api-edi.urssaf.fr`, same path and scope) and the test API "bac à sable"
  (`api.urssaf.fr` + `/api-odspn-be-platforms-sandbox/v1`, scope `ods.pn.platforms.sandbox`). Today the enum only has
  `Sandbox` / `Production`; extend it (host, base path, scope) using the `UrssafEndpoints` constants (`TEST_API_*`
  already hold the third target).
- `Urssaf\Http\OAuth2TokenProvider`, `JsonApiClient`, `RetryAfter` (reuse the Tiers de prestations design); token cache
  keyed by target + scope. Token host for the sandbox to confirm (api-reference §5).
- `Urssaf\Http\MultipartBodyBuilder` (PSR-17 streams, `name` + `data` parts), binary download as stream.
- `Urssaf\UrssafHttpGateway` + `Urssaf\Wire\*` mappers (`period` string vs list, dates, `UCN-*` pagination headers,
  report file references).
- `Urssaf\Exception\ApiError` + error payload parser (array of `{code, message, description}`; codes not published).
- `ApiConfiguration` (target, overridable host, platform id, `Accept-Language` default `FR`).

## Bridges

- Symfony bundle `UrssafPlateformeNumeriqueBundle` (alias `urssaf_plateforme_numerique`): configuration (credentials,
  target, base URI, platform id, language), HTTP client, token cache pool, console commands
  (`urssaf:pn:identification:upload`, `urssaf:pn:declaration:upload`, `urssaf:pn:submission:status`, `urssaf:pn:news`).
- Doctrine bridge: only if state is needed, i.e. an optional submission log (urssafRef, type, file name, period, last
  known `status` / `paymentStatus`) to track asynchronous processing (api-reference §4.11). TBD.

## Runtime dependencies to add with the code

`ext-json`, `ext-mbstring`, `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/clock`, `psr/log`,
`psr/simple-cache` (or `psr/cache`), plus the matching `suggest` entries (Symfony bridge).

## Tests

- Unit: value objects, mappers against `tests/Fixtures/wire/*.json` (hand-built fixtures for `Identification`,
  `Declaration` and errors, flagged as such), multipart builder, handlers with fake gateway, `Environment` for the three
  targets.
- Functional: Symfony bundle wiring.
- Contract (`make test-contract`, manual CI job), on the **sandbox** (`api-edi`) and on the **test API**: credentials
  `URSSAF_SANDBOX_CLIENT_ID/SECRET` (and a second pair if the test API needs its own), optional `URSSAF_SANDBOX_BASE_URI`,
  a platform id. First goals: settle the token host, the multipart `name` / `data` semantics, error codes and the
  `lastUpdateDate` time zone; check that `news-feed` is absent on the test API; look for the endpoint behind the orphan
  `UserIdentification*` schemas.

## Order of work

1. Extend `Environment` to the three targets; domain value objects and enums.
2. Wire mappers + fixtures.
3. HTTP gateway (OAuth2, errors, 429 handling, multipart, downloads).
4. Application commands / queries.
5. Symfony bridge (Doctrine log only if confirmed).
6. Contract tests, then remove the open points from `api-reference.md`.
