# ADR 0012 — Symfony bridge

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md),
[ADR 0003](0003-unknown-upload-outcome.md), [ADR 0004](0004-submission-lifecycle.md),
[ADR 0007](0007-tenant-model.md) and [ADR 0009](0009-personal-data.md).

## Context

The reference ships a Symfony 6.4 / 7.x bundle. Several URSSAF packages may be installed in the same application.

## Decision

- Names: alias and root `urssaf_plateforme_numerique`, service ids, parameter, tag and translation domain with that
  prefix; console namespace `urssaf:pn:`; lock prefix `urssaf_pn`; Monolog channel `urssaf_pn`; schedule
  `urssaf_pn`; recipe variables `URSSAF_PN_ENV`, `URSSAF_PN_CLIENT_ID`, `URSSAF_PN_CLIENT_SECRET`,
  `URSSAF_PN_PLATFORM_ID`.
- Configuration: `environment` (`production` | `sandbox` | `test_api`), `base_uri`, `accept_language`, `api_timezone`,
  `credentials.<tenant>.{client_id, client_secret, platform_id}` or `credentials_provider` + `platform_resolver`,
  `token_store`, `http_client`, `gateway.simulated`, `rate_limiter`, `lock`, `outbox`, `scheduler`, `workflow`,
  `persistence` (`doctrine` default when the ORM is installed, `in_memory`, `custom`), `messenger`.
- Commands: `urssaf:pn:identification:upload`, `urssaf:pn:declaration:upload`, `urssaf:pn:submissions:sync`,
  `urssaf:pn:submissions:reconcile`, `urssaf:pn:submission:resolve`, `urssaf:pn:submission:show`,
  `urssaf:pn:report:download`, `urssaf:pn:news`, `urssaf:pn:status`, `urssaf:pn:outbox:relay`,
  `urssaf:pn:outbox:purge`, `urssaf:pn:check`. Exit codes `0`, `1` (failure), `2` (invalid usage).
- Messenger: `SyncTenantSubmissionsMessage` and `ReconcileTenantSubmissionsMessage` (tenant id only, safe to retry). No
  upload message (the content is not stored, [ADR 0004](0004-submission-lifecycle.md)).
- Lock: polling and reconciliation per tenant; uploads per tenant and declaration type
  ([ADR 0008](0008-concurrent-declaration-guard.md)).
- Commands print codes and package messages, never URSSAF descriptions, `userId` or `userEmail`.

## Consequences

- The bundle and the other URSSAF bundles share nothing.
