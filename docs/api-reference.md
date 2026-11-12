# URSSAF "API portail Plateforme numérique" — technical reference

This document is the single source of truth used by the package for enums, validation rules and wire fixtures.
It summarises the official specification. One package covers **two catalogue entries that share the same resources**:

| Catalogue entry | `apiId` | Swagger (OpenAPI 3.0.1) | Files |
|---|---|---|---|
| **Échanges réels** (real exchanges) | `portail-pn-echanges-reels` | **v1.0.0**, production and sandbox | `docs/openapi/portail-pn-echanges-reels-prd.json`, `…-sb.json`, `docs/reference/api-portail-plateforme-numerique-echanges-reels.yaml` |
| **Bac à sable** (technical test API) | `portail-pn-bac-a-sable` | **v1.0.0**, production host only | `docs/openapi/portail-pn-bac-a-sable-prd.json`, `docs/reference/api-portail-plateforme-numerique-bac-a-sable.yaml` |

- Catalogue pages: https://portailapi.urssaf.fr/fr/catalogue-api/prd/portail-pn-echanges-reels and
  https://portailapi.urssaf.fr/fr/catalogue-api/prd/portail-pn-bac-a-sable
- Technical documentation PDF: **none published** for this API.

The package constant `SpecVersion::CURRENT` must equal `1.0.0`. Rule shared by the URSSAF packages: `SpecVersion` is
the version in the **file name of the published PDF** (the most detailed source), falling back to the Swagger version
when there is no versioned PDF. Here there is no PDF, so the value comes from the Swagger (`info.version` = `1.0.0` in
the three JSON files and the two YAML files). Field names, codes and wire values below are reproduced verbatim from the
specification (they are French or English by design of the contract); everything else in the package is English.

Source of truth for the types: the three JSON contracts (the files served by the portal) have **empty response
schemas** (`"schema": {}`, `components.schemas` is `{}`, examples are Jackson `JsonNode` dumps such as
`"nodeType": "OBJECT"`). Only the official YAML files carry the data model (`Identification`, `Declaration`, …) and
the real examples. Paths, operation ids, parameters, descriptions and response codes are identical in JSON and YAML
(checked programmatically); the other differences are listed in §5.

> Status: specification only. Nothing below is implemented yet; see [roadmap.md](roadmap.md).

---

## 1. General

| Topic | Value |
|---|---|
| Purpose | Within the withholding at source of the social contributions of auto-entrepreneurs by digital platforms: lets a platform automate exchanges with URSSAF. **Échanges réels**: submit monthly identification files (`DPID`), declaration / regularisation files (`DPDE` / `DPRE`), follow their asynchronous processing, download the reports, read the notification feed. **Bac à sable**: same file exchanges for the platform's technical tests. |
| Access | Both APIs require a **prior registration on the "portail Plateforme numérique"**. Subscription conditions and delays are not published. |
| Security | OAuth2 **client credentials** (`securitySchemes.oauth2`, global `security` on every operation) |
| Token endpoint | `POST https://api.urssaf.fr/api/oauth/v1/token` (form-urlencoded: `grant_type=client_credentials`, `client_id`, `client_secret`, `scope`), declared as `tokenUrl` in all contracts, including the sandbox one (see §5) |
| Scopes | Échanges réels (both platforms): `ods.pn.platforms`. Bac à sable: `ods.pn.platforms.sandbox` |
| Base URLs | see table below |
| Content types | Requests: no body except the two **`multipart/form-data` uploads** (§2.1, §2.5). Responses: `application/json`; file downloads: `application/octet-stream` (binary) |
| Headers | Request: `Accept-Language` (optional, `FR` or `EN`, default `FR`, drives the language of labels and `newsText`). Response (paginated lists and news feed): `UCN-Total-Pages`, `UCN-Total-Count`, `UCN-Current-Page`, `UCN-Per-Page` |
| HTTP errors | 400, 401, 403, 404 (single-resource reads and downloads only), 500. **No 429 and no 503 are declared** |

Targets (three, all with the same resources except where noted):

| Target | Host | Base path | Scope | Operations |
|---|---|---|---|---|
| Échanges réels — production | `https://api.urssaf.fr` | `/api-odspn-be-platforms/v1` | `ods.pn.platforms` | 9 (with `GET …/news-feed`) |
| Échanges réels — sandbox ("environnement Éditeur") | `https://api-edi.urssaf.fr` | `/api-odspn-be-platforms/v1` | `ods.pn.platforms` | 9 |
| Bac à sable — technical test API (served from the **production host**) | `https://api.urssaf.fr` | `/api-odspn-be-platforms-sandbox/v1` | `ods.pn.platforms.sandbox` | 8 (**no** `news-feed`) |

Full base URLs: `https://api.urssaf.fr/api-odspn-be-platforms/v1`, `https://api-edi.urssaf.fr/api-odspn-be-platforms/v1`,
`https://api.urssaf.fr/api-odspn-be-platforms-sandbox/v1`. The YAML files declare `http://api.urssaf.fr/…` instead of
`https://` and list the production server only (§5). In the package, `Environment` currently models production
(`https://api.urssaf.fr`) and sandbox (`https://api-edi.urssaf.fr`); `UrssafEndpoints` already holds `TOKEN_PATH`,
`TOKEN_SCOPE`, `API_BASE_PATH` and the `TEST_API_TOKEN_SCOPE` / `TEST_API_BASE_PATH` constants of the third target.
See §4.1.

The sandbox/production Swagger files of Échanges réels differ **only** by `servers[0].url`.

### 1.1 Quotas

No quota is documented (no `429` declared). Values are **not published**.

### 1.2 Known limitations (official, from the operation descriptions)

- Uploads are **asynchronous**: a `200` only means the file was received and queued; the outcome must be polled with the
  matching `GET …/{urssafRef}` until `status` leaves `IN_PROCESS`.
- Identification: the upload window is constrained by the identification calendar period (uploads outside it "may be
  rejected"); only one identification file per platform per period "should" be submitted.
- Declaration: only **one `DECLARATION` and one `REGULARISATION` can be in process at a time** per platform. A new file of
  the same type is accepted by the API (`200`) but rejected by the processing system, with a global report carrying a
  specific anomaly.
- Declaration (`DECLARATION`): window constrained by the declaration calendar period. `REGULARISATION`: can be submitted at
  any time.
- "The platform must have production access enabled to submit files outside of sandbox mode" (both upload operations).
- Sandbox notifications are prefixed with `"Sandbox"` in `newsText`.
- The DPID / DPDE / DPRE XSD schemas, the calendars, and the report file formats are **not published** in this
  documentation set.

### 1.3 Error payload

Error responses (400, 401, 403, 404, 500) are all declared as an **array** of `Error` objects (`components.schemas.Error`
in the YAML; `items: {}` in the JSON):

| Field | Type | Rules |
|---|---|---|
| `code` | string | "Code of the error". Values **not published** |
| `message` | string | "A message describing the error" |
| `description` | string | no description |

```json
[ { "code": "…", "message": "…", "description": "…" } ]
```

No official error example exists; the codes and the 401/403 payload produced by the API gateway (as opposed to the
backend) are not documented (§5). There is no fixture for errors.

---

## 2. Operations

Nine method+path pairs are published for Échanges réels, eight of them (all but `news-feed`) for Bac à sable. Every
operation is available on the three targets of §1 unless noted. Operation ids, summaries and descriptions are the same on
both products.

Common parameters (components `PlatformIdPathParameters`, `AcceptLanguage`, `Search`, `PageNumber`, `PageSize`, `Sort`):

| Field | In | Type | Req. | Rules |
|---|---|---|---|---|
| `platformId` | path | string | yes | "ID of the platform", e.g. `4300027`. Format not published |
| `Accept-Language` | header | string | no | enum `FR`, `EN`; default `FR` (upper case as in the contract) |
| `search` | query | string | no | "Search keyword to filter platforms by name or business name", e.g. `Plateforme X`. Present on the two submission searches, **absent** from `news-feed`. Matching semantics not published |
| `page` | query | integer | no | minimum 1, e.g. `1`. Default not published |
| `size` | query | integer | no | minimum 1, maximum **500**, e.g. `10`. Default not published |
| `sort` | query | array of string | no | YAML examples: `["period:ASC"]`, `["period:DESC"]`, `["period:ASC", "lastUpdateDate:DESC"]` (so *inferred from the examples*: `field:ASC|DESC`, repeatable). Sortable fields not published beyond `period` and `lastUpdateDate`. Default order: `newsDate` descending on `news-feed`; not published for the searches |
| `urssafRef` | path | string | yes | unique Urssaf reference of a submission, as returned by the matching `…/upload`; example `cbcf2700-110d-4abf-9835-a9b46e68d7c4` |
| `fileRef` | path | string | yes | reference of a report file, taken from `<report>.fileId` of the submission; example `a1b2c3d4-e5f6-7890-abcd-ef1234567890` |
| `start_date`, `end_date` | query | string, `format: date` | no | see §2.3 / §2.7 |

Pagination response headers (components `UCNTotalPages`, `UCNTotalCount`, `UCNCurrentPage`, `UCNPerPage`), declared
`required: true` with `type: string` and numeric examples (`8`, `150`, `2`, `20`): `UCN-Total-Pages`, `UCN-Total-Count`,
`UCN-Current-Page`, `UCN-Per-Page` ("Total number of pages", "Total number of results", "Current page", "Number of element
per page").

Shared structures (YAML `components.schemas`; the JSON contracts have none):

`File` (multipart body of both uploads):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `name` | string | yes (per descriptions) | "name of the document"; must follow the file naming convention (DPID / DPDE / DPRE), see the operations |
| `data` | string, `format: binary` | yes (per descriptions) | binary content of the compressed XML file (`.xml.gz`) |

`FileRef` (report reference):

| Field | Type | Rules |
|---|---|---|
| `name` | string | "name of the document" |
| `fileId` | string | value to pass as `fileRef` to the download operations |

`Identification`:

| Field | Type | Rules |
|---|---|---|
| `identificationFileRef` | string, `uuid` | example `cd48ba8a-3a7e-4b80-8e87-f14a5542c6cf`. Relation to `urssafRef` not documented |
| `identificationFileName` | string | example `DPID_202505_24001_000_20250428150823.xml.gz` |
| `period` | string | example `2025-05` (`YYYY-MM` *inferred from the example*). A **string** here, an array on `Declaration` |
| `urssafRef` | string, `uuid` | example `cd48ba8a-3a7e-4b80-8e87-f14a5542c6cf` |
| `userId` | string | "User's id who uploaded the identification file." |
| `userEmail` | string | "User's email who uploaded the declaration file." (sic, copy-paste in the contract) |
| `status` | `FileStatus` | §3.1 |
| `statusLabel` | string | example `File successfully Processed`; localised by `Accept-Language` *(inferred)* |
| `lastUpdateDate` | string, `date-time` | example `2025-04-28T20:04:14` (**no time zone** in the example) |
| `uploadDate` | string | "Date of the upload of the file". No `format` declared |
| `treatmentDate` | string | "Date when the identification was processed by Urssaf". No `format` declared |
| `globalReportRef` | `FileRef` | always produced |
| `detailedReportRef` | `FileRef` | only when the file is accepted |
| `usersAnomaliesReportRef` | `FileRef` | only when accepted and at least one declared user has anomalies |
| `affiliatedUsersFileRef` | `FileRef` | only when accepted and at least one user falls within the scope of the scheme |
| `details` | `IdentificationDetails` | `numberOfDeclaredUser`, `numberOfIdentifiedUser`, `numberOfError` (all `number`, not `integer`) |

`Declaration`:

| Field | Type | Rules |
|---|---|---|
| `declarationFileRef` | string, `uuid` | example `cd48ba8a-3a7e-4b80-8e87-f14a5542c6cf` |
| `declarationFileName` | string | example `DPDE_202505_24001_000_20250428150823.xml.gz` |
| `declarationType` | `DeclarationType` | §3.3 |
| `period` | array of string | example `["2025-04", "2025-05"]` (one month for `DECLARATION`, one or more for `REGULARISATION`) |
| `urssafRef` | string | example `unique-file-submission-id-12345` (**not a UUID**, unlike `Identification`; no `format`) |
| `userId`, `userEmail` | string | uploader id / email |
| `status` | `FileStatus` | §3.1 |
| `statusLabel` | string | example `File successfully Processed` |
| `paymentStatus` | string enum | §3.2 (defined inline here, no `$ref`) |
| `paymentStatusLabel` | string | no description |
| `lastUpdateDate` | string, `date-time` | example `2025-04-28T20:04:14` (no time zone) |
| `uploadDate`, `treatmentDate` | string | same as `Identification`; no `format` |
| `globalReportRef`, `detailedReportRef`, `usersAnomaliesReportRef` | `FileRef` | availability as for `Identification` (no `affiliatedUsersFileRef`) |
| `statistics` | `DeclarationDetails` | `numberOfDeclaredUser`, `totalRevenue`, `debitAmount`, `paymentAmount`, `numberOfError` (all `number`); currency and unit **not published** |
| `concernedRegularisation` | string | no description |

No schema marks any field `required`.

### 2.1 Upload an identification file — `POST /platforms/{platformId}/identifications/upload`

`operationId`: `uploadPlatformIdentification` (tag `Identifications`). Summary: "Upload a platform identification file."
Submits a **monthly** identification file; it must follow the DPID format and naming convention (example in the
description `DPID_4300001_2026-05_001.xml.gz`).

Parameters: `platformId` (path), `Accept-Language` (header), see the common table.

Body: `multipart/form-data`, `File` (see above). The `name` field follows the DPID naming convention and `data` holds the
binary of the compressed XML. Official request example (`name` only): `tests/Fixtures/wire/upload_identification_request.json`.

Response 200 (`application/json`): object with one property

| Field | Type | Rules |
|---|---|---|
| `urssafRef` | string | "Unique Urssaf reference identifying this file submission." example `cbcf2700-110d-4abf-9835-a9b46e68d7c4`. To be retained to poll the status |

Fixture `tests/Fixtures/wire/upload_response.json` (object assembled from the property-level `example`; the contract has
no object-level example).

Errors: 400, 401, 403, 500 (no 404). Business rules: see §1.2.

### 2.2 Retrieve an identification submission — `GET /platforms/{platformId}/identifications/{urssafRef}`

`operationId`: `getPlatformIdentification` (tag `Identifications`). Returns the details and processing status of a
submission. Poll after the upload until `status` leaves `IN_PROCESS`. Result files are listed in the `…ReportRef` /
`affiliatedUsersFileRef` fields (conditions in the `Identification` table) and downloaded through §2.4.

Parameters: `platformId`, `Accept-Language`, `urssafRef` (path).

Response 200: `Identification` (see above), `status` values in §3.1.

Errors: 400, 401, 403, 404, 500. No official full response example (only property-level examples, summarised above).

### 2.3 Search identification submissions — `GET /platforms/{platformId}/identifications`

`operationId`: `searchPlatformIdentification` (tag `Identifications`). Paginated history of the platform's identification
submissions, **all statuses**, ordered by `sort`. Each item is the full `Identification` object.

Parameters: `platformId`, `search`, `page`, `size`, `sort`, `Accept-Language` (common table) and

| Field | Type | Req. | Rules |
|---|---|---|---|
| `start_date` | string, `date` | no | submissions whose withholding `period` is on or after the **month** of this date. Format `YYYY-MM-DD`; the day is ignored. No lower bound when omitted. YAML example `2025-04-01` (JSON example: `2025-04-01T00:00:00.000Z`, see §5) |
| `end_date` | string, `date` | no | same for the upper bound (on or before the month). YAML example `2025-04-30` |

Response 200: array of `Identification` (empty array when nothing matches) with the four `UCN-*` headers.

Errors: 400, 401, 403, 500 (no 404).

### 2.4 Download an identification result file — `GET /platforms/{platformId}/identifications/{urssafRef}/file/{fileRef}`

`operationId`: `downloadIdentificationRelatedFile` (tag `Identifications`). Downloads one result file of a submission.
`fileRef` comes from `globalReportRef.fileId`, `detailedReportRef.fileId`, `usersAnomaliesReportRef.fileId` or
`affiliatedUsersFileRef.fileId` of §2.2.

Parameters: `platformId`, `Accept-Language`, `urssafRef`, `fileRef`.

Response 200: `application/octet-stream`, `string` / `binary` ("File retrieved successfully."). File name, size and inner
format (XML? CSV?) are **not published**; no `Content-Disposition` is documented.

Errors: 400, 401, 403, 404, 500.

### 2.5 Upload a declaration or regularisation file — `POST /platforms/{platformId}/declarations/upload`

`operationId`: `uploadPlatformDeclaration` (tag `Declarations`). Summary: "Upload a platform declaration or regularisation file."

Parameters: `platformId`, `Accept-Language` and

| Field | In | Type | Req. | Rules |
|---|---|---|---|---|
| `type` | query | `DeclarationType` | **yes** | `DECLARATION` (single withholding month, DPDE XSD, constrained by the declaration calendar) or `REGULARISATION` (one or more months, DPRE XSD, any time). Enum in §3.3 |

Body: `multipart/form-data`, `File`. `name` follows the naming convention (**DPDE** for declarations, **DPRE** for
regularisations); `data` holds the binary of the compressed XML. Official request examples (`name` only):
`tests/Fixtures/wire/upload_declaration_request_declaration.json` (`DPDE_202505_24001_000_20250428150823.xml.gz`) and
`tests/Fixtures/wire/upload_declaration_request_regularisation.json` (`DPRE_202505_24001_000_20250428150823.xml.gz`).

Response 200: same object as §2.1 (`urssafRef`), fixture `upload_response.json`. "A `200` response does not guarantee the
file will be accepted by the processing system": the outcome is read through §2.6.

Errors: 400, 401, 403, 500 (no 404). Business rules: see §1.2 (one `DECLARATION` and one `REGULARISATION` in process at a
time; same-type concurrent upload accepted then rejected asynchronously).

### 2.6 Retrieve a declaration submission — `GET /platforms/{platformId}/declarations/{urssafRef}`

`operationId`: `getPlatformDeclaration` (tag `Declarations`). Returns the details, **processing status** (`status`) and
**payment status** (`paymentStatus`) of a declaration or regularisation submission. The two fields are independent and
must be read separately (semantics in §3.1 and §3.2). Report files (`globalReportRef`, `detailedReportRef`,
`usersAnomaliesReportRef`) are downloaded through §2.8.

Parameters: `platformId`, `Accept-Language`, `urssafRef`.

Response 200: `Declaration` (see above).

Errors: 400, 401, 403, 404, 500.

### 2.7 Search declaration submissions — `GET /platforms/{platformId}/declarations`

`operationId`: `searchPlatformDeclaration` (tag `Declarations`). Paginated history of declarations and regularisations,
all statuses and payment statuses. Each item is the full `Declaration` object.

Parameters: `platformId`, `search`, `page`, `size`, `sort`, `Accept-Language` and

| Field | In | Type | Req. | Rules |
|---|---|---|---|---|
| `type` | query | `DeclarationType` | no | filters by type; both types returned when omitted |
| `start_date` | query | string, `date` | no | at least one withholding `period` on or after the month of this date (`YYYY-MM-DD`, day ignored). Example `2025-04-01` |
| `end_date` | query | string, `date` | no | at least one withholding `period` on or before the month of this date. Example `2025-04-30` |

Response 200: array of `Declaration` (empty if no match) with the four `UCN-*` headers.

Errors: 400, 401, 403, 500 (no 404).

### 2.8 Download a declaration report file — `GET /platforms/{platformId}/declarations/{urssafRef}/file/{fileRef}`

`operationId`: `downloadDeclarationRelatedFile` (tag `Declarations`). Downloads one report of a submission; `fileRef` comes
from `globalReportRef.fileId`, `detailedReportRef.fileId` or `usersAnomaliesReportRef.fileId` of §2.6.

Parameters: `platformId`, `Accept-Language`, `urssafRef`, `fileRef`. Response 200: `application/octet-stream` (binary),
same remarks as §2.4. Errors: 400, 401, 403, 404, 500.

### 2.9 Retrieve the notification feed — `GET /platforms/{platformId}/news-feed`

`operationId`: `getPlatformNewsFeed` (tag `Platforms`). **Échanges réels only (production and sandbox); not published in
Bac à sable.** Paginated notifications, ordered by `newsDate` descending by default (changeable with `sort`), generated by
the system for key events (a file successfully uploaded, new report files available). `newsText` is a human-readable
message localised by `Accept-Language`.

Parameters: `platformId`, `page`, `size`, `sort`, `Accept-Language` (**no** `search`, no date filter).

Response 200: array of `NewsFeed` with the four `UCN-*` headers (empty array when none):

| Field | Type | Rules |
|---|---|---|
| `idNews` | string | notification id (UUID *inferred from the example*) |
| `newsText` | string | message; sandbox notifications start with `Sandbox` |
| `newsDate` | string, `date-time` | RFC 3339 with microseconds and `Z` in the example |

Official example: `tests/Fixtures/wire/news_feed_response.json` (two items; the file names quoted there use a
different naming pattern, see §5).

Errors: 400, 401, 403, 500 (no 404).

### 2.10 Fixtures

`tests/Fixtures/wire/` contains the only examples found in the contract (YAML `example` / `examples`), copied verbatim:
`upload_identification_request.json`, `upload_declaration_request_declaration.json`,
`upload_declaration_request_regularisation.json`, `news_feed_response.json`, plus `upload_response.json` (assembled from
the property-level example). There is no official example of `Identification`, `Declaration` or error payloads, nor of
the binary downloads; those fixtures will be hand-built from the property examples and flagged as such when the mappers
are written. The JSON contracts' examples are unusable (see header).

---

## 3. Reference values

### 3.1 `FileStatus` (`status`)

| Value | Meaning |
|---|---|
| `IN_PROCESS` | file received and being processed |
| `ACCEPTED` | processed successfully; reports available depending on the content |
| `REJECTED` | file rejected; the global report (always available) details the reasons |

### 3.2 `paymentStatus` (`Declaration` only)

| Value | Meaning |
|---|---|
| `NOT_INITIATED` | no payment action yet; also the status after a previously initiated payment is cancelled |
| `DECLARED` | a bank transfer was declared on the portal but funds not yet received; back to `NOT_INITIATED` if the declared transfer expires |
| `PROCESSING` | a PIS payment was initiated on the portal, funds not yet received; back to `NOT_INITIATED` if the PIS payment fails |
| `INTERLOCUTION` | the file was rejected because the declared contribution amount differs from the amount received; a corrective file was submitted but the interlocution with URSSAF is not finalised; back to `NOT_INITIATED` once finalised |
| `PROCESSED` | URSSAF received a payment. File `IN_PROCESS`: match unknown yet; `REJECTED`: discrepancy detected; `ACCEPTED`: full flow complete. Regularisations without additional contribution go directly to `PROCESSED` |

The contract lists them in the order `NOT_INITIATED`, `PROCESSING`, `INTERLOCUTION`, `DECLARED`, `PROCESSED`.

### 3.3 `DeclarationType` (`type`, `declarationType`)

`DECLARATION`, `REGULARISATION`.

### 3.4 `Accept-Language`

`FR` (default), `EN`.

### 3.5 Result and report files

| Field | Identification | Declaration | Produced |
|---|---|---|---|
| `globalReportRef` | yes | yes | always (accepted or rejected) |
| `detailedReportRef` | yes | yes | only if accepted |
| `usersAnomaliesReportRef` | yes | yes | only if accepted and at least one declared user has anomalies |
| `affiliatedUsersFileRef` | yes | no | only if accepted and at least one user is within the scope of the scheme |

### 3.6 Bac à sable orphan schemas (not reachable from any path)

The Bac à sable YAML defines schemas used by **no** path of either contract: `UserIdentificationRequest`
(`period`, `userToIdentify` required), `UserToIdentify` (`id` required, pattern `^(\d{14}|\d{9})$`: SIRET or SIREN,
`firstName`, `lastName`, `birthDate` `YYYY-MM-DD`, `placeOfBirth`, `contactDetails`), `ContactDetails`, `PostalAddress`,
`UserIdentificationResponse` (`period`, `userToIdentify`, `reconciliationStatus`, `anomalies`, `rate`) and
`IdentificationRate` (`code`, `rate`). Their descriptions mention a *synchronous single-user identification*.
`reconciliationStatus` is an integer enum: `0` identification impossible, `1` within the scope of the scheme (rates in
`rate`), `2` outside the scope. Examples: anomaly codes `PNID_RT41`, `PNID_RT42`; rates `{"code":"NA01","rate":20.152}`,
`{"code":"NA02","rate":20.00}`. No corresponding endpoint is published (§5); nothing is implemented from these.

---

## 4. Package decisions derived from the specification

1. **Three targets, one resource model.** `Environment` must model real production, real sandbox (`api-edi`) and the
   test API ("bac à sable"). Each target carries host, base path and scope (§1). `Environment` today only has
   `Sandbox` / `Production` with `baseUri()`; it must be extended (e.g. a third case for the test API, whose
   `baseUri()` is `https://api.urssaf.fr`) and expose base path and scope, reusing the `UrssafEndpoints` constants
   (`API_BASE_PATH`, `TEST_API_BASE_PATH`, `TOKEN_SCOPE`, `TEST_API_TOKEN_SCOPE`). TBD: enum case name and whether
   host and base path stay overridable independently (host must stay overridable by configuration).
2. **News feed on the test API.** The operation does not exist there. Decision: the gateway fails locally with a
   dedicated exception rather than sending a request that would 404. TBD, to confirm by contract test.
3. **Uploads.** The package sends a file it is given (name + stream/path); it **does not generate or compress** DPID /
   DPDE / DPRE XML (XSDs not published). The multipart body (`name`, `data` parts) is built in Infrastructure with PSR-17
   streams. TBD: how the `name` part relates to the file name of the `data` part (the contract only shows `name`), and
   local validation of the naming convention (three observed patterns, §5): none until confirmed.
4. **`type` is explicit.** `DeclarationType` is a required argument of the declaration upload; no inference from the
   file prefix.
5. **Asynchronous model.** Upload returns an `UrssafReference` only. No automatic polling in the gateway; an optional
   application-level `waitUntilProcessed` (interval/timeout configurable, stops when `status != IN_PROCESS`) is TBD.
   `status` and `paymentStatus` are modelled as independent enums with a raw fallback for unknown values.
6. **Pagination.** Searches return a `Page` (items + total pages/count/current/per-page parsed from the `UCN-*`
   headers; headers tolerated as absent since declared as strings). `size` is capped client-side at 500.
7. **Dates.** `start_date` / `end_date` sent as `YYYY-MM-DD`. `period` is a `YYYY-MM` value object (a single value on
   identifications, a list on declarations). `lastUpdateDate` has no time zone in the example: parsed as
   `Europe/Paris` — TBD until confirmed. `uploadDate` / `treatmentDate` have no declared format: kept as raw string
   with a best-effort parse — TBD. `newsDate` is an RFC 3339 instant.
8. **Downloads.** Returned as a stream/binary value, never decoded; no assumption on file name or inner format.
9. **Errors.** Array payload parsed into a list of `{code, message, description}`; unknown codes preserved raw. HTTP
   429/503 handled generically (Retry-After) even though not declared.
10. **`Accept-Language`.** Configurable, default `FR` (the API default), sent in upper case as in the contract.
11. **Persistence.** The API is stateless on the client side, but uploads are asynchronous and `urssafRef` must be
    retained. A Doctrine submission log (urssafRef, type, name, period, last known `status` / `paymentStatus`) is a
    possible optional bridge; TBD.
12. **Personal data.** `userId`, `userEmail` (and, in the orphan schemas, names, birth data, address, phone, e-mail) are
    personal data: never logged. Reports may contain personal data of the platform's users.
13. **Source of types.** Models follow the YAML schemas; the JSON contracts only confirm paths, parameters and codes.
14. **Orphan schemas (§3.6).** Not implemented until the endpoint is published.

---

## 5. Open points

Discrepancies between sources:

- **JSON vs YAML**: the three JSON contracts have empty schemas and Jackson-dump examples (`nodeType`, `valueNode`, …
  for the multipart bodies, the `sort` examples and the news-feed example). The YAML files carry the real model and
  examples. The 200 response of the upload operations is `{}` in JSON, an object with `urssafRef` in YAML.
- **`servers`**: JSON `https://api.urssaf.fr/…` (prd), `https://api-edi.urssaf.fr/…` (sb); YAML `http://api.urssaf.fr/…`
  with `description: Main server` and no sandbox host.
- **`start_date` / `end_date` examples**: JSON `2025-04-01T00:00:00.000Z` / `2025-04-30T00:00:00.000Z`, YAML `2025-04-01` /
  `2025-04-30`; both declare `format: date` and the description says `YYYY-MM-DD`.
- **prd vs sb (Échanges réels JSON)**: only `servers[0].url`. The `tokenUrl` stays `https://api.urssaf.fr/api/oauth/v1/token`
  in the sandbox file; `UrssafEndpoints::TOKEN_PATH` is relative to the environment host. Token host for `api-edi` to
  confirm.
- **Échanges réels vs Bac à sable**: the Bac à sable has no `news-feed` operation and no `Platforms` tag, uses scope
  `ods.pn.platforms.sandbox` and base path `/api-odspn-be-platforms-sandbox/v1` on the **production** host; its YAML
  additionally defines the orphan schemas of §3.6. Paths 1–8 are otherwise identical (descriptions included, which still
  talk about "sandbox mode" and "production access").
- **Orphan Bac à sable schemas**: probably a published-later or unpublished *synchronous single-user identification*
  endpoint (path, method, status codes unknown).
- **Inconsistencies inside the YAML**: `Identification.urssafRef` is a `uuid`, `Declaration.urssafRef` is a free string
  (example `unique-file-submission-id-12345`); `period` is a string on `Identification` and an array on `Declaration`;
  `Identification.userEmail` says "declaration file"; `search` is described as filtering "platforms by name or business
  name" although the endpoints return submissions; `lastUpdateDate` is `date-time` but its example has no zone;
  `uploadDate` / `treatmentDate` have no format; counters are `number`; the pagination headers are `type: string` with
  numeric examples; file name patterns: description `DPID_4300001_2026-05_001.xml.gz`, examples
  `DPID_202505_24001_000_20250428150823.xml.gz` / `DPDE_…` / `DPRE_…`, news feed `PNID_2025-11_4309999_001.xml.gz`.

Unknowns (**not published**):

- Quota values, 429/503 behaviour, timeouts.
- Subscription process and delays on the "portail Plateforme numérique"; meaning of "production access enabled" and
  "sandbox mode" on the real API vs the test API; whether the test API and `api-edi` share credentials/data.
- Error codes (`Error.code`), 401/403 payload shape from the gateway.
- Defaults of `page` / `size`, default sort of the searches, list of sortable fields, `search` semantics.
- Formats of `platformId`, `urssafRef`, `fileRef`, `idNews`; DPID / DPDE / DPRE naming convention, XSDs, calendars,
  maximum file size, expected `Content-Type` of the `data` part.
- Content, name and format of report and result files; unit and currency of `statistics`.
- Time zone of `lastUpdateDate`, `uploadDate`, `treatmentDate`.
- Whether `status` / `paymentStatus` values may be extended (enums are closed in the contract).
