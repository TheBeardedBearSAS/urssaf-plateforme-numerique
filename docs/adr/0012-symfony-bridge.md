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

## Amendment (2026-11-16, lead-accepted, lots 5-8)

Compared with what `src/Bridge/Symfony` ships:

- Commands: the twelve names above are the ones shipped (`Bridge\Symfony\Command`). Some of them are registered only
  under a condition:
  - `urssaf:pn:status` (and `GetWorkflowOverviewHandler`) only when a `WorkflowStatistics` is available. It always is,
    except with the `custom` driver without `persistence.statistics`;
  - `urssaf:pn:outbox:relay` and `urssaf:pn:outbox:purge` only with `outbox.enabled`.

  The Messenger handlers are registered with `messenger.enabled` or `scheduler.enabled`, since the schedule dispatches
  the messages. A command whose lock is held prints a message and exits with `0`.
- Configuration (`BundleConfiguration`): besides the keys listed above, the bundle ships:
  - top-level keys: `request_id_header`, `token_safety_margin`, `http_timeout` (default HTTP client only), and
    `tenants` (the tenants processed by the commands and the schedule that cannot discover them; default: the keys of
    `credentials`);
  - the sub-keys of the listed sections:
    - `token_store.{cache_pool, service}`;
    - `rate_limiter.{enabled, max_calls, window_seconds}`;
    - `lock.{enabled: auto|true|false, factory, ttl}`;
    - `outbox.{enabled, sink: messenger|event_dispatcher|custom, service, retention}`;
    - `scheduler.{enabled, sync_frequency, reconcile_frequency}`;
    - `workflow.{status_poll_interval, payment_poll_interval, max_polling_age, max_upload_attempts,
      stale_upload_after, manual_review_after, reconciliation_max_pages, concurrent_declaration_guard,
      default_page_size, max_page_size}`;
    - `persistence.{driver, entity_manager, repository, statistics, transaction_manager, outbox}`;
    - `messenger.{enabled, bus}`.

  `docs/symfony.md` is the reference for their types and defaults.
- Lock (`Bridge\Symfony\Lock\OperationLocks`, `urssaf_pn.<operation>.<scope>`): it is taken by the console commands
  and the Messenger handlers only, never by a use case handler called directly. The scopes are:
  - `identification.upload`: per tenant;
  - `declaration.upload`: per tenant and declaration type;
  - `submissions.sync` and `submissions.reconcile`: per tenant;
  - `outbox.relay`: one lock for the whole relay (scope `*`).

## Amendment (2026-11-17, lead-accepted, release 0.1.0)

- The Flex recipe of `recipe/thebeardedbear/urssaf-plateforme-numerique/0.1/` is submitted, unchanged, to
  symfony/recipes-contrib ([symfony/recipes-contrib#2123](https://github.com/symfony/recipes-contrib/pull/2123)), as the other URSSAF packages did. `recipe/` stays its source: a change of the
  recipe adds a version directory here and is submitted upstream in the same form. `bin/build-flex-endpoint` and the
  CI job `flex-endpoint` are kept, as in the CESU package, for hosts that serve the recipe from their own endpoint
  until the upstream merge.
