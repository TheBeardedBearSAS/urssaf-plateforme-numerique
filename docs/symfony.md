# Symfony bundle

Bundle class: `TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\UrssafPlateformeNumeriqueBundle` (alias
`urssaf_plateforme_numerique`), for Symfony 6.4 and 7.x. Register it in `config/bundles.php`. Everything the bundle
declares is prefixed, so that it never collides with another URSSAF package installed in the same application
([ADR 0012](adr/0012-symfony-bridge.md)):

| Kind | Name |
|---|---|
| Configuration root, service ids, parameter, tag, translation domain | `urssaf_plateforme_numerique` |
| Console commands | `urssaf:pn:*` |
| Lock names | `urssaf_pn.<operation>.<scope>` |
| Monolog channel | `urssaf_pn` |
| Scheduler schedule (transport `scheduler_urssaf_pn`) | `urssaf_pn` |
| Flex recipe variables | `URSSAF_PN_ENV`, `URSSAF_PN_CLIENT_ID`, `URSSAF_PN_CLIENT_SECRET`, `URSSAF_PN_PLATFORM_ID` |

## Symfony Flex recipe

The recipe lives in `recipe/thebeardedbear/urssaf-plateforme-numerique/<version>/` (manifest and copied files), in the
layout of [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib), where it is published: copy the version
directory there in a pull request when a release changes it (Flex picks the highest recipe version not above the
installed package version). It registers the bundle for every environment, copies
`config/packages/urssaf_plateforme_numerique.yaml` and adds the following variables to `.env` (empty values: fill them
in `.env.local` or in your secrets, never commit them):

| Variable | Default | Role |
|---|---|---|
| `URSSAF_PN_ENV` | `sandbox` | `production`, `sandbox` or `test_api`. |
| `URSSAF_PN_CLIENT_ID` | empty | OAuth2 client id of the account. |
| `URSSAF_PN_CLIENT_SECRET` | empty | OAuth2 client secret of the account. |
| `URSSAF_PN_PLATFORM_ID` | empty | Identifier of the platform declared to URSSAF. |

The copied file declares one tenant (`default`) from these variables and, in the `test` environment, enables the
simulated gateway (see [Testing](#testing-with-the-simulated-gateway)). A test boots the bundle with the copied
configuration, so that the recipe follows the configuration tree.

To try a recipe change before it is published, or to serve it from your own endpoint, `bin/build-flex-endpoint
<output-dir> <base-url>` builds a Flex endpoint (`index.json` and one JSON file per recipe) to serve over HTTPS at
`<base-url>` and declare in `extra.symfony.endpoint` of the application (`["<base-url>/index.json", "flex://defaults"]`).

## Configuration reference

The configuration tree is defined in `Bridge\Symfony\DependencyInjection\BundleConfiguration`.

| Key | Type | Default | Description |
|---|---|---|---|
| `environment` | enum `production` \| `sandbox` \| `test_api` | required | Target platform. Usually `'%env(URSSAF_PN_ENV)%'`, resolved at runtime (an empty variable fails when the gateway is first used). The hosts and the OAuth2 scope of each environment are in [ADR 0001](adr/0001-targets.md). |
| `base_uri` | string \| null | `null` | Overrides the host of the selected environment (a proxy); the base path and the scope still follow the environment. |
| `accept_language` | enum `FR` \| `EN` | `FR` | Value of the `Accept-Language` header. |
| `api_timezone` | string | `Europe/Paris` | Time zone of the zone-less timestamps written by URSSAF ([ADR 0006](adr/0006-numbers-and-dates.md)); validated against `DateTimeZone::listIdentifiers()`. |
| `request_id_header` | string \| null | `null` | Name of the optional request id header (a UUID per attempt); null: not sent. |
| `token_safety_margin` | int >= 0 | `60` | Seconds before expiry from which an access token is renewed. |
| `http_client` | string \| null | `null` | Service id of a PSR-18 client. Default: a dedicated Symfony HttpClient wrapped in `Psr18Client`. |
| `http_timeout` | float >= 0.1 | `15.0` | Timeout in seconds of the default HTTP client. |
| `credentials` | map tenant id => `{client_id, client_secret, platform_id}` | `[]` | Static credentials and platform by tenant (the three keys are required, non-empty). Use `%env(...)%` placeholders. |
| `credentials_provider` | string \| null | `null` | Service id of a `CredentialsProvider`. Used together with `platform_resolver`, instead of `credentials`. |
| `platform_resolver` | string \| null | `null` | Service id of a `PlatformResolver`. Used together with `credentials_provider`. |
| `tenants` | list of string | keys of `credentials` | Tenants processed by the commands and the schedule that cannot discover them. Required in practice with `credentials_provider`. |
| `token_store.cache_pool` | string | `cache.app` | Cache pool (PSR-6) that stores the access tokens. Recommended: a **dedicated pool** (see below). |
| `token_store.service` | string \| null | `null` | Service id of a `TokenStore`; takes precedence over the cache pool. |
| `gateway.simulated` | bool | `false` | Tests only: replaces the URSSAF API with the in-memory `Testing\SimulatedPlatformGateway`, exposed as a public service to script the scenarios (see [testing.md](testing.md)). Refused with `environment: production`. |
| `rate_limiter.enabled` | bool | `false` | Limits the calls to URSSAF per tenant, in the current process (URSSAF publishes no quota, [ADR 0002](adr/0002-http-client-adaptations.md)). |
| `rate_limiter.max_calls` | int >= 1 | `60` | Calls per tenant within the window. |
| `rate_limiter.window_seconds` | int >= 1 | `60` | Window length in seconds. |
| `lock.enabled` | `auto` \| bool | `auto` | Non-blocking lock per operation (see [Locks](#locks-against-overlapping-runs)). `auto`: enabled when `symfony/lock` is installed and the factory service exists; `true`: required (the container fails to build without `symfony/lock`, or without the factory service); `false`: disabled. |
| `lock.factory` | string | `lock.factory` | Service id of a `Symfony\Component\Lock\LockFactory`. |
| `lock.ttl` | float >= 1 | `3600.0` | Seconds after which the lock of a crashed process expires. Set it above the longest run of a command. |
| `outbox.enabled` | bool | `false` | Writes the integration events in an outbox, in the transaction of each state change (see [integration-events.md](integration-events.md)). Registers `urssaf:pn:outbox:relay` and `urssaf:pn:outbox:purge`. |
| `outbox.sink` | enum `messenger` \| `event_dispatcher` \| `custom` | `messenger` | Where `urssaf:pn:outbox:relay` delivers the events: the Messenger bus `messenger.bus`, the Symfony event dispatcher, or your own `IntegrationEventSink`. |
| `outbox.service` | string \| null | `null` | Service id of your `IntegrationEventSink` (required with the `custom` sink). |
| `outbox.retention` | ISO 8601 duration | `P30D` | How long a dispatched entry is kept (`urssaf:pn:outbox:purge`). |
| `scheduler.enabled` | bool | `false` | Registers the Symfony Scheduler schedule `urssaf_pn` (see [Scheduler](#scheduler)). Requires `symfony/scheduler`. |
| `scheduler.sync_frequency` | ISO 8601 duration \| null | `PT10M` | Interval between two runs of `urssaf:pn:submissions:sync`; null leaves the task out. |
| `scheduler.reconcile_frequency` | ISO 8601 duration \| null | `PT15M` | Interval between two runs of `urssaf:pn:submissions:reconcile`; null leaves the task out. |
| `workflow.status_poll_interval` | ISO 8601 duration | `PT10M` | Interval between two reads of a submission in process ([ADR 0004](adr/0004-submission-lifecycle.md)). |
| `workflow.payment_poll_interval` | ISO 8601 duration | `PT6H` | Interval between two reads of the payment status of an accepted declaration. |
| `workflow.max_polling_age` | ISO 8601 duration | `P1Y` | How long a submission is still polled after its upload, whatever its status: a submission is never followed forever. |
| `workflow.max_upload_attempts` | int >= 1 | `Application\Policy\WorkflowPolicy::DEFAULT_MAX_UPLOAD_ATTEMPTS` | Upload attempts after which a submission is not uploaded again automatically. |
| `workflow.stale_upload_after` | ISO 8601 duration > 0 | `PT1H` | How long a submission may stay `uploading` before it is considered stale and moved to `outcome_unknown` ([ADR 0003](adr/0003-unknown-upload-outcome.md)). |
| `workflow.manual_review_after` | ISO 8601 duration | `P1D` | How long a submission may stay `outcome_unknown` before it is reported as needing a manual review. |
| `workflow.reconciliation_max_pages` | int >= 1 | `Application\Policy\WorkflowPolicy::DEFAULT_RECONCILIATION_MAX_PAGES` | Search pages read to reconcile one submission whose outcome is unknown. |
| `workflow.concurrent_declaration_guard` | bool | `true` | Refuses a declaration while another one of the same type is in process ([ADR 0008](adr/0008-concurrent-declaration-guard.md)). |
| `workflow.default_page_size` | int >= 1 | `Application\Policy\WorkflowPolicy::DEFAULT_PAGE_SIZE` | Records loaded by a batch use case when no limit is given. |
| `workflow.max_page_size` | int >= 1 | `Application\Policy\WorkflowPolicy::MAX_PAGE_SIZE` | Upper bound of the records loaded by a batch use case. |
| `persistence.driver` | enum `in_memory` \| `doctrine` \| `custom` | `doctrine` if `doctrine/orm` is installed, else `in_memory` | Persistence adapter. |
| `persistence.entity_manager` | string | `default` | Entity manager that gets the mapping (driver `doctrine`). **Use a dedicated entity manager** (see [Doctrine](#doctrine)): the repository flushes the whole unit of work of the entity manager it uses. |
| `persistence.repository` | string \| null | `null` | Service id of your `SubmissionRepository` (driver `custom`, required). |
| `persistence.statistics` | string \| null | `null` | Service id of your `WorkflowStatistics` (driver `custom`, optional: without it `GetWorkflowOverviewHandler` and `urssaf:pn:status` are not registered). |
| `persistence.transaction_manager` | string \| null | `null` | Service id of your `TransactionManager` shared with the repository (driver `custom`; default: `ImmediateTransactionManager`). |
| `persistence.outbox` | string \| null | `null` | Service id of your `Outbox` (required with the `custom` driver when the outbox is enabled). Default: the outbox of the persistence driver. |
| `messenger.enabled` | bool | `false` | Registers the Messenger handlers (always registered when `scheduler.enabled` is on). |
| `messenger.bus` | string | `messenger.default_bus` | Bus of the `messenger` outbox sink. |

The durations are validated as ISO 8601 intervals and the time zone against `DateTimeZone::listIdentifiers()`.

Validation rules enforced at container build time:

- `credentials` and `credentials_provider` / `platform_resolver` are mutually exclusive, and one of the two forms is required.
- `credentials_provider` and `platform_resolver` must be configured together.
- `environment` must be `production`, `sandbox` or `test_api` (an environment placeholder is checked at runtime).
- `gateway.simulated` cannot be used with `environment: production`. When `environment` is an environment variable
  placeholder, the container cannot know its value at build time: the check is repeated at runtime, and a simulated
  gateway whose placeholder resolves to `production` is refused when it is first used.
- `lock.enabled: true` requires `symfony/lock`.
- `scheduler.enabled: true` requires `symfony/scheduler`.
- `outbox.enabled` with the `custom` sink requires `outbox.service`.
- `outbox.enabled` with the `custom` persistence driver requires `persistence.outbox`.
- The `custom` persistence driver requires `persistence.repository`.
- `workflow.default_page_size` must not exceed `workflow.max_page_size`.
- `workflow.stale_upload_after` must be greater than zero.
- `api_timezone` must be a known time zone and every duration a valid ISO 8601 interval.

The `in_memory` driver keeps everything in the process memory: use it for tests only.

### Credentials of several tenants

Static credentials, one entry per tenant ([ADR 0007](adr/0007-tenant-model.md)):

```yaml
urssaf_plateforme_numerique:
    environment: '%env(URSSAF_PN_ENV)%'
    credentials:
        default:
            client_id: '%env(URSSAF_PN_CLIENT_ID)%'
            client_secret: '%env(URSSAF_PN_CLIENT_SECRET)%'
            platform_id: '%env(URSSAF_PN_PLATFORM_ID)%'
```

Credentials stored in your database: implement `Application\Port\CredentialsProvider` and
`Application\Port\PlatformResolver`, then reference both services (and list the tenants the commands and the schedule
process):

```yaml
urssaf_plateforme_numerique:
    environment: '%env(URSSAF_PN_ENV)%'
    credentials_provider: App\Urssaf\DatabaseCredentialsProvider
    platform_resolver: App\Urssaf\DatabasePlatformResolver
    tenants: [tenant-a, tenant-b]
```

### Dedicated cache pool for the access tokens

The access tokens are stored in the `token_store.cache_pool` pool (`cache.app` by default). Declare a dedicated pool, so
that `cache:pool:clear cache.app` (a routine deployment step) does not force a new token request for every tenant, and
so that the tokens live in an adapter you control (Redis with authentication, for instance):

```yaml
framework:
    cache:
        pools:
            urssaf_pn.token_cache:
                adapter: cache.adapter.redis
                provider: '%env(REDIS_DSN)%'

urssaf_plateforme_numerique:
    token_store: { cache_pool: urssaf_pn.token_cache }
```

The pool must be shared by every process that calls URSSAF (web, workers, cron): a per-process `array` adapter would
request a token per run. Tokens are secrets: do not expose the pool through a cache dashboard or a shared namespace.

## Console commands

All commands print the codes and the messages of the package, never the descriptions written by URSSAF nor the
`userId` / `userEmail` of a submission ([ADR 0009](adr/0009-personal-data.md)). `--tenant` is optional where the
command can fall back on the configured `tenants`; an invalid option or a missing required one is a usage error.

| Command | Purpose |
|---|---|
| `urssaf:pn:identification:upload <file> --external-reference= [--name=] [--tenant=]` | Upload an identification file. |
| `urssaf:pn:declaration:upload <file> --type=DECLARATION\|REGULARISATION --external-reference= [--name=] [--tenant=]` | Upload a declaration file. Refused while another declaration of the same type is in process (`workflow.concurrent_declaration_guard`). |
| `urssaf:pn:submissions:sync [--tenant] [--limit] [--dry-run]` | Read the submissions in process (and the payment status of the accepted declarations) that are due for a poll. |
| `urssaf:pn:submissions:reconcile [--tenant] [--limit] [--dry-run]` | Settle the uploads whose outcome is unknown by searching them at URSSAF ([ADR 0003](adr/0003-unknown-upload-outcome.md)). |
| `urssaf:pn:submission:resolve <id> --urssaf-ref=<reference>\|--not-received --tenant` | Record the outcome of an unknown upload after a manual check (an operator decides). |
| `urssaf:pn:submission:show <id> --tenant [--format=table\|json]` | Show one submission. |
| `urssaf:pn:report:download <id> <fileId> --output=<path> --tenant` | Download a report file of a submission to `<path>` ([ADR 0005](adr/0005-files.md)). |
| `urssaf:pn:news [--tenant] [--page] [--size]` | Read the news feed (not available on `test_api`). |
| `urssaf:pn:status [--format]` | Count the submissions by tenant and state (local database only; registered when the persistence offers statistics); `--format=json` for monitoring. |
| `urssaf:pn:outbox:relay` | Deliver the pending integration events to the sink (only with `outbox.enabled`). |
| `urssaf:pn:outbox:purge` | Delete the outbox entries dispatched for longer than `outbox.retention` (only with `outbox.enabled`). |
| `urssaf:pn:check [--skip-remote]` | Check the installation (see below). |

Exit codes: `0` on success, `1` on failure (an item ended in error, or a package exception stopped the command), `2` on
invalid usage (an unknown or missing option, an unreadable file, no tenant to process). A command that cannot take its
lock because another run holds it prints a message and exits with `0`: the run that holds the lock does the work (see
[Locks](#locks-against-overlapping-runs)). `--dry-run` takes no lock and calls nothing.

The upload commands compute the content fingerprint of the file and read it from disk: the content is never stored
by the package ([ADR 0004](adr/0004-submission-lifecycle.md)).

### Checking the installation

`urssaf:pn:check` prints one line per check and exits with `1` when one of them fails (warnings do not fail): run it
after each deployment, or in the CI of the application with `--skip-remote`.

| Check | Failure / warning |
|---|---|
| `configuration` | failure: the configuration cannot build the API configuration (for example an unknown environment, once the placeholder is resolved) |
| `credentials` | failure: no credentials or platform for a tenant in the configured provider |
| `authentication` | failure: URSSAF refuses the credentials or cannot be reached. A new access token is requested for every tenant (any cached one is ignored), the tokens of the application are not touched. Skipped with `--skip-remote`; replaced by a `gateway` warning when `gateway.simulated` is enabled |
| `token store` | warning: the tokens are stored in `cache.app` instead of a dedicated pool |
| `persistence` | warning: the `in_memory` driver is selected |
| `database schema` | failure (driver `doctrine`): the package tables are missing or differ from the mapping; warning: the entity manager is the `default` one |
| `lock` | warning: the lock is disabled or `symfony/lock` is missing |
| `messenger` | skipped: `messenger.enabled` is off (ok when on: the handlers are registered, the outbox bus is named) |
| `scheduler` | skipped: `scheduler.enabled` is off (ok when on: names the schedule and its `messenger:consume scheduler_urssaf_pn` transport) |

Add checks of your own by implementing `Bridge\Symfony\Diagnostic\InstallationCheck` (autoconfigured, tag
`urssaf_plateforme_numerique.installation_check`): they run after the package checks, unless their tag has a positive
`priority`.

## Messenger

With `messenger.enabled: true`, the following messages (namespace `Bridge\Symfony\Message`) are handled by the handlers
of `Bridge\Symfony\MessageHandler`. They are plain `final readonly` classes holding the tenant id only, so they
serialize with any Messenger serializer and are safe to retry.

| Message | Property | Effect |
|---|---|---|
| `SyncTenantSubmissionsMessage` | `tenantId` | Does what `urssaf:pn:submissions:sync --tenant=<tenantId>` does. |
| `ReconcileTenantSubmissionsMessage` | `tenantId` | Does what `urssaf:pn:submissions:reconcile --tenant=<tenantId>` does. |

There is no upload message: the content of a file is not stored ([ADR 0004](adr/0004-submission-lifecycle.md)), so an
upload is always an explicit call (the command, or `UploadIdentificationHandler` / `UploadDeclarationHandler`).

Route the messages in your Messenger configuration:

```yaml
framework:
    messenger:
        routing:
            'TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\SyncTenantSubmissionsMessage': async
            'TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\ReconcileTenantSubmissionsMessage': async
```

Failure mapping:

- `RetryableException` (URSSAF unavailable, rate limit exceeded...) => `RecoverableMessageHandlingException`: the
  message is retried by the retry strategy of the transport, with the delay requested by URSSAF (`Retry-After`) when
  known. The delay needs a symfony/messenger version whose `RecoverableMessageHandlingException` accepts it; on older
  versions (6.4) the exception carries no delay and the retry strategy of the transport alone decides.
- Operation already running for the tenant (lock held) => `RecoverableMessageHandlingException` with a 30 s delay: the
  message is retried later instead of being lost or run concurrently (see [Locks](#locks-against-overlapping-runs)).
- Any other `UrssafException`, and an upload whose outcome is unknown (`OutcomeUnknown`) =>
  `UnrecoverableMessageHandlingException`: retrying the same message cannot succeed (it goes straight to the failure
  transport).
- Failures of other origins (database down...) are left to the retry strategy of the host.

### Integration events on Messenger

With `outbox.enabled` and the default `messenger` sink, `urssaf:pn:outbox:relay` dispatches every integration event
(`Contract\Event`) on the bus `messenger.bus`. Write a handler for the events you care about
([integration-events.md](integration-events.md)).

## Scheduler

Instead of the crontab, the bundle can register a [Symfony Scheduler](https://symfony.com/doc/current/scheduler.html)
schedule named `urssaf_pn` that synchronises and reconciles the submissions of every configured tenant, through
Messenger: same locks, same reports, same safety nets. It dispatches the `SyncTenantSubmissionsMessage` and
`ReconcileTenantSubmissionsMessage` of each tenant, their handlers are registered whenever the scheduler is enabled (`messenger.enabled` is only needed to consume the messages from your own transports).

```bash
composer require symfony/scheduler
```

```yaml
urssaf_plateforme_numerique:
    scheduler:
        enabled: true
        sync_frequency: PT10M
        reconcile_frequency: PT15M   # null leaves the task out
    messenger: { enabled: true }     # optional: only to consume the messages from your own transports
```

Run one worker for the schedule (and keep it running, like any Messenger worker):

```bash
php bin/console messenger:consume scheduler_urssaf_pn
```

Run a single scheduler worker (or enable the lock): two workers of the same schedule would run each task twice.

## Locks against overlapping runs

Two crons that overlap, a cron racing a Messenger worker, or two workers handling the same tenant could poll or
reconcile the same submissions at the same time. The atomic claims keep that safe but wasteful. When `symfony/lock` is
installed, every upload, synchronisation and reconciliation (command or Messenger handler) takes a **non-blocking**
lock named `urssaf_pn.<operation>.<scope>` before it works, and releases it afterwards:

| Operation | Used by | Scope |
|---|---|---|
| uploads | `urssaf:pn:identification:upload`, `urssaf:pn:declaration:upload` | tenant, and declaration type for a declaration ([ADR 0008](adr/0008-concurrent-declaration-guard.md)) |
| synchronisation | `urssaf:pn:submissions:sync`, `SyncTenantSubmissionsMessage` | tenant |
| reconciliation | `urssaf:pn:submissions:reconcile`, `ReconcileTenantSubmissionsMessage` | tenant |

When the lock is already held: a **command** prints that the operation is already running and exits with `0` (the run
that holds the lock does the work); a **Messenger handler** throws `RecoverableMessageHandlingException` with a 30 s
delay, so the message is retried by the retry strategy of the transport. `--dry-run` takes no lock. A tenant that is
busy is skipped, the other tenants of the same run are still processed.

Setup:

```bash
composer require symfony/lock
```

```yaml
framework:
    lock: '%env(LOCK_DSN)%'   # redis://..., or `flock`; must be shared by every host that runs the crons / workers

urssaf_plateforme_numerique:
    lock: { enabled: auto, factory: lock.factory, ttl: 3600 }
```

The store must be visible to all the processes that may overlap: `flock` only protects processes of the same machine,
use a Redis / database / Zookeeper store otherwise. `ttl` bounds how long the lock of a crashed process blocks the
operation. Without `symfony/lock` (or with `enabled: false`) everything just runs, protected by the claims only.

## Doctrine

When the driver is `doctrine` and DoctrineBundle is registered, the bundle prepends the Doctrine configuration: the
custom DBAL types and the XML mapping (see [doctrine.md](doctrine.md)). With a non-default `entity_manager`, the mapping
is attached to that entity manager only.

**Use a dedicated entity manager** (`persistence.entity_manager: urssaf_pn`, with its own DBAL connection). The
repository `flush()`es on every save and a Doctrine flush writes *all* pending changes of the entity manager: on a
shared entity manager, a package call would also flush (and, on failure, roll back) half-finished changes of your own
entities.

The bundle does not touch `doctrine.orm.auto_generate_proxy_classes`. On PHP 8.4 or higher, with an ORM that implements
them, the bundle also prepends the native lazy objects setting; your own `doctrine` configuration always wins. The
details, the tables and the migrations are in [doctrine.md](doctrine.md).

## Validation

The use cases return their violations as `ValidationFailed`. When `symfony/validator` is installed, the bundle registers
the public service `Bridge\Symfony\Validation\ViolationListConverter`, which turns them into a Symfony
`ConstraintViolationList`: the property path is the violation path, the code and the message template are the violation
code, the message is its translation in the domain `urssaf_plateforme_numerique` (current locale of the `translator`,
or the English message without translator), and the invalid value is always `null`: the rejected raw values (personal
data) are never exposed.

## Translations

The violation codes (`<subject>.<reason>`, for example `external_reference.too_long`) are translatable messages of the
translation domain `urssaf_plateforme_numerique`. The bundle ships English and French:
`Resources/translations/urssaf_plateforme_numerique.en.yaml` and `urssaf_plateforme_numerique.fr.yaml` (same keys, one
per violation code of the package, kept in sync by the test suite).

To add another locale: create `translations/urssaf_plateforme_numerique.<locale>.yaml` in your application, with the same
keys as the English file. To reword a shipped message, define the key in your application's file of that locale:

```yaml
# translations/urssaf_plateforme_numerique.fr.yaml
"credentials.not_found": "Aucun identifiant d'API n'est configuré pour ce tenant."
```

Keys defined in your application's `translations/` directory override the shipped messages. Translate a violation with
`$translator->trans($violation->code(), [], 'urssaf_plateforme_numerique')`.

## Testing with the simulated gateway

Enable the in-memory URSSAF platform in the test environment only (the Flex recipe does it):

```yaml
# config/packages/urssaf_plateforme_numerique.yaml
when@test:
    urssaf_plateforme_numerique:
        gateway: { simulated: true }
```

The gateway port is then the `Testing\SimulatedPlatformGateway`, a public service you script from your `KernelTestCase`
(see [testing.md](testing.md)). Nothing is sent to URSSAF, and `urssaf:pn:check` reports the `gateway` line as a warning
instead of requesting a token. The container refuses `gateway.simulated: true` with `environment: production`, at build
time and, when `environment` is an environment variable placeholder, again at runtime: a production variable can never
reach the simulator.

For a persistence-free test, use `persistence: { driver: in_memory }`; for a test of your Doctrine mapping, use a
dedicated entity manager on an in-memory SQLite connection and create the schema with the Doctrine `SchemaTool`.
