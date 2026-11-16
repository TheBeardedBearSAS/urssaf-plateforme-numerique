# ADR 0002 — HTTP client adaptations

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The reference `JsonApiClient` sends JSON bodies, `x-request-id` and `recouv.application.id`, and classifies errors
with a published code catalogue. This contract has two `multipart/form-data` uploads, two binary downloads
(`application/octet-stream`), an optional `Accept-Language` header (`FR` / `EN`), paginated responses with `UCN-*`
headers, an array error payload whose codes are not published, and no quota (api-reference §1, §1.3, §2).

## Decision

- `Http\ApiRequest` has `get(operation, path, query)`, `multipart(operation, path, query, MultipartFile)` and
  `download(operation, path)`. Query strings are encoded with RFC 3986; an array parameter (`sort`) is repeated
  (`sort=period:ASC&sort=lastUpdateDate:DESC`), the OpenAPI 3 default for a query array (`style: form`,
  `explode: true`, nothing else declared).
- Headers: `Authorization`, `Accept` (`application/json`; `application/octet-stream, application/json` on downloads),
  `Accept-Language` (configured, default `FR`, sent in upper case), `User-Agent`. No request-id or application-id
  header by default; `ApiConfiguration` accepts an optional request-id header name (value: a UUID per attempt from the
  `IdGenerator` port), as in the CESU package, for when URSSAF asks for one.
- Multipart body: two parts, `name` (text, the file name) and `data` (the file content, `filename` = the same name,
  `Content-Type: application/octet-stream`, the generic type of `format: binary`). The body is assembled in a
  `php://temp` stream (memory-bounded) from the PSR-17 factories; the boundary comes from the `IdGenerator` port. The
  relation between `name` and the `filename` of `data` is not published (§5) and is checked by the contract tests.
- Status mapping: 2xx success; 400 `UrssafRequestRejected` (errors parsed); 401 → invalidate the token and replay once,
  then `UrssafAuthenticationFailed`; 403 `UrssafAuthenticationFailed`; 404 `UrssafResourceNotFound` (single-resource
  reads and downloads, not retryable); 429 and 5xx `UrssafUnavailable` (with `Retry-After` seconds when present);
  anything else `UnexpectedUrssafResponse`. 429 and 503 are not declared but handled generically (§4.9). The upload
  operations follow [ADR 0003](0003-unknown-upload-outcome.md).
- Token endpoint: 400 / 401 / 403 `UrssafAuthenticationFailed`, 429 and 5xx `UrssafUnavailable::whileAuthenticating`.
- Error bodies (400, 401, 403, 404, 429, 5xx) are parsed leniently into `ApiErrors` (`code`, `message`,
  `description`; a list, a single object or an unreadable body give the matching list or `none()`), kept on the
  exception and never logged.
- Pagination: `UCN-Total-Pages`, `UCN-Total-Count`, `UCN-Current-Page`, `UCN-Per-Page` are parsed as non-negative
  integers; an absent or non-numeric header gives `null` (declared `type: string`, §4.6).
- Downloads: the response body is wrapped in an `Application\Port\Urssaf\BinaryStream` adapter, never buffered nor
  decoded ([ADR 0005](0005-files.md)); `Content-Type` and `Content-Disposition` are exposed raw when present.
- Transport failures are sanitised: the exceptions keep the class name of the PSR-18 exception, never its message (it
  embeds the URI, hence the `search` keyword).
- Token cache key: `CacheKey::of('urssaf_pn.' . sha256(target | base URI | scope | client id))`; the secret is never
  part of the key.
- Rate limiting: the `RateLimiter` port is kept; the default is `NoopRateLimiter` because no quota is published
  (§1.1). `GatewayOptions::withRateLimiter()` accepts the `SlidingWindowRateLimiter` (configurable calls per window,
  per tenant, all operations together).

## Consequences

- The client stays an internal class, as in the reference; only its request shapes and header policy differ.
- When the contract tests settle a header or the multipart semantics, the change is configuration or a one-line
  mapping change.

## Amendment (2026-11-16, lead-accepted, lot 4)

- `ApiConfiguration` keeps the reference constructor shape: `(Environment, ?string $baseUriOverride, ?string
  $requestIdHeader, string $acceptLanguage = 'FR', int $tokenSafetyMarginSeconds = 60, string $userAgent,
  DateTimeZone $apiTimeZone = Europe/Paris)`; the request-id header takes the slot of the reference `applicationId`
  (a `= null` default is forbidden by `ergebnis.noParameterWithNullDefaultValue`). `forEnvironment()` passes null.
- 404 maps to `UrssafResourceNotFound` only on `getPlatformIdentification`, `getPlatformDeclaration` and the two
  downloads; on a search or the news feed it is an `UnexpectedUrssafResponse` (undeclared status).
- The token cache key `'urssaf_pn.' . sha256(…)` is longer than 64 characters, so `CacheKey` stores it as
  `hashed_<sha256>`; the key stays deterministic and never contains the secret.
- The gateway exceptions for 401/403 and the upload 5xx do not carry the parsed `ApiErrors` yet (port signatures of
  lot 3); the error bodies are still never logged.

## Amendment (2026-11-16, lead-accepted, lot 8)

- The relation between the `name` part and the `filename` of the `data` part is checked only when the opt-in contract tests are run against the sandbox (`make test-contract`, or the manual
  `contract` job), and no test settles it: the test sends the same value in both. Until such a run, the point stays open in [docs/api-reference.md](../api-reference.md) §5.
