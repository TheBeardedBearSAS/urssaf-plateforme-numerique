# Doctrine persistence

The Doctrine bridge (`TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine`) persists the submission aggregate and
the integration outbox with Doctrine ORM 3 / DBAL 4 (`composer.json` declares a `conflict` with `doctrine/orm < 3.0` and
`doctrine/dbal < 4.0`). The domain classes carry no Doctrine metadata: the mapping is a set of XML files in
`src/Bridge/Doctrine/Resources/mapping`. Doctrine is an optional dependency (`suggest`): require `doctrine/dbal` and
`doctrine/orm` (and `doctrine/doctrine-bundle` with Symfony) in your application. See
[ADR 0011](adr/0011-doctrine-bridge.md).

Classes: `DoctrineSubmissionRepository` (implements `SubmissionRepository`), `DoctrineWorkflowStatistics` (implements
`WorkflowStatistics`), `DoctrineOutbox` (implements `Outbox`), `DoctrineTransactionManager` (implements
`TransactionManager`), `DoctrineIntegration` (types, mapping driver, bundle configuration), plus the DBAL types
(`Bridge\Doctrine\Type`) and the JSON serializers.

## Tables

All tables and DBAL types are prefixed with `urssaf_pn_` (`DoctrineIntegration::TABLE_PREFIX`), so that nothing
collides with the other URSSAF packages (`urssaf_tps_`, `urssaf_cesu_`).

| Table | Entity | Content |
|---|---|---|
| `urssaf_pn_submissions` | `Domain\Submission\Submission` | One row per file sent to the platform (see below). |
| `urssaf_pn_outbox` | `Bridge\Doctrine\Entity\OutboxMessage` | Integration events waiting to be relayed (`Bridge\Doctrine\Repository\DoctrineOutbox`, written with DBAL in the transaction of the state change). See [integration-events.md](integration-events.md). |

### Columns of `urssaf_pn_submissions`

| Column | Content |
|---|---|
| `id` | Local submission id (primary key, assigned by the application). |
| `version` | Optimistic lock token (see [Concurrency](#concurrency-and-claim)). |
| `tenant_id`, `platform_id`, `external_reference` | Identity: the tenant, the URSSAF platform, the reference given by the host. |
| `kind`, `declaration_type` | What was sent (identification or declaration; the declaration type is null for an identification). |
| `file_name`, `fingerprint` | Name of the uploaded file and fingerprint of its content (the content itself is never stored). |
| `state`, `attempts` | Local lifecycle state ([ADR 0004](adr/0004-submission-lifecycle.md)) and number of upload attempts. |
| `urssaf_ref` | Reference returned by URSSAF once the upload is known. |
| `last_polled_at`, `raw_status`, `raw_payment_status` | Last poll instant and the raw statuses reported by URSSAF, kept in sync with the snapshot. |
| `snapshot` (JSON) | Last known URSSAF answer: periods, report file references, counters, last update date. |
| `errors` (JSON) | Code and message of each error of a refused upload. |
| `created_at`, `updated_at` | Instants (UTC). |

Constraints and indexes:

- unique `(tenant_id, external_reference)`: one submission per external reference in a tenant;
- `(tenant_id, state, last_polled_at)` for the polling queries;
- `(tenant_id, urssaf_ref)` for the lookups by URSSAF reference;
- `(tenant_id, declaration_type, state)` for the concurrent declaration guard ([ADR 0008](adr/0008-concurrent-declaration-guard.md)).

## No progress table

Everything a query filters or sorts on is a real, indexed column: state, kind, declaration type, `urssaf_ref`, the raw
URSSAF status and payment status, the last poll instant and the creation instant. Only the data that no query filters
on (the snapshot of the last poll and the errors of a refused upload) is stored as JSON. There is therefore no
query-side "progress" table to keep in sync, and the repository writes the aggregate alone.

## Registering types and mapping without Symfony

```php
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineIntegration;

DoctrineIntegration::registerTypes();                                          // idempotent, global DBAL type registry
$configuration->setMetadataDriverImpl(DoctrineIntegration::mappingDriver());   // SimplifiedXmlDriver
```

`DoctrineIntegration::types()` returns the custom types by name (`urssaf_pn_*`), `mappingDirectory()` the XML
directory, `MAPPING_NAMESPACE` the namespace prefix handled by the simplified driver, and
`doctrineBundleConfiguration()` the array that the Symfony bundle prepends to the `doctrine` extension.

If your application maps its own entities too, chain the drivers: give `mappingDriver()` the namespaces
`TheBeardedBear\UrssafPlateformeNumerique\Domain` and `...\Bridge`, and your own driver your namespaces.

### Lazy loading and PHP 8.4

Doctrine ORM 3 loads lazy references either through generated proxies built on the lazy ghosts of `symfony/var-exporter`
6.4 / 7 (the only option before PHP 8.4, and the default), or through the native lazy objects of PHP 8.4 (ORM 3.4+). The
lazy ghosts are gone from `symfony/var-exporter` 8: with that version, and without native lazy objects, the ORM throws
"Symfony LazyGhost is not available" as soon as it builds its proxy factory.

Without the Symfony bundle, enable the native objects yourself on PHP 8.4+:

```php
if (DoctrineIntegration::nativeLazyObjectsAvailable()) {   // PHP >= 8.4 and an ORM that implements them
    $configuration->enableNativeLazyObjects(true);
}
```

`doctrineBundleConfiguration()` adds `orm.enable_native_lazy_objects: true` under the same condition
(`doctrineBundleConfigurationWith([...])` takes the ORM options explicitly).

### Wiring

With the framework-free builder ([standalone.md](standalone.md)):

```php
$package = UrssafPlateformeNumeriqueBuilder::create($transport, $credentials, $platforms, $configuration)
    ->withDoctrine($entityManager)                                  // repository, statistics, transaction manager
    ->withOutbox(new DoctrineOutbox($entityManager), $sink)   // optional
    ->build();
```

or by hand:

```php
$submissions = new DoctrineSubmissionRepository($entityManager);
$statistics = new DoctrineWorkflowStatistics($entityManager);
$transactions = new DoctrineTransactionManager($entityManager);
```

`Submission` is not declared `final`, so that Doctrine can generate its lazy-loading proxies with any
`auto_generate_proxy_classes` mode (including pre-generation at cache warm-up); it is final by convention (`@final`) and
must not be extended. Value objects are mapped as embeddables and custom DBAL types.

## Dedicated entity manager (strongly recommended)

The repository `persist()`s and then `flush()`es the entity manager it is built with on **every** write. A Doctrine
flush has no per-entity variant (`flush($entity)` was removed in ORM 3): it writes **all** the pending changes of the
unit of work, host entities included. If the package shares the entity manager of your application, a write made by
the package would therefore also flush half-finished changes of your own entities (and a rollback of the package
transaction would roll them back too).

Use a **dedicated entity manager** (and connection) for the package tables:

```yaml
doctrine:
    dbal:
        connections:
            default: { url: '%env(resolve:DATABASE_URL)%' }
            urssaf:  { url: '%env(resolve:URSSAF_DATABASE_URL)%' }   # same database is fine, different connection
    orm:
        entity_managers:
            default: { connection: default, mappings: { App: { type: attribute, dir: '%kernel.project_dir%/src/Entity', prefix: App\Entity } } }
            urssaf:  { connection: urssaf, mappings: { UrssafPlateformeNumerique: { type: xml, is_bundle: false, dir: '<DoctrineIntegration::mappingDirectory()>', prefix: 'TheBeardedBear\UrssafPlateformeNumerique' } } }
```

When you must share the entity manager, flush your own changes before calling a package use case, and accept that the
use case flushes whatever is pending. `DoctrineTransactionManager` never calls `EntityManager::clear()`, so even on a
shared entity manager a failed package operation does not detach your entities.

## Personal data

[ADR 0009](adr/0009-personal-data.md): the package stores **nothing personal**. The submissions table has no user id
and no user email column (a test asserts it), no file content and no declared-person data: the file name, the content
fingerprint, the tenant, the platform and the references are technical data. There is consequently nothing to encrypt
in the package tables, and no erasure API to call. The host application keeps the link between a submission and its
author (the acting user) in its own tables, keyed by the submission id or the external reference, under its own
retention and erasure policy.

Backups keep what was deleted afterwards: bound their retention. The package never logs personal data.

## Migrations

The package ships **no migration**: the schema depends on your platform and on the rest of your schema. Generate it in
your application:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

(or `doctrine:schema:update --dump-sql` for a quick look). Re-run `diff` after each upgrade of the package: the mapping
may evolve (see the changelog).

## Transactions

`DoctrineTransactionManager::transactional()` runs the operation in a DBAL transaction. The repository flushes on every
write, so those flushes join the surrounding transaction and become durable together with the outbox rows. On failure
the transaction is rolled back, the `Submission` entities still managed by the entity manager are detached so that none
of them keeps a state that was rolled back, and the throwable is rethrown unchanged. `DoctrineTransactionManager` neither
clears nor closes the entity manager; a failed flush inside the operation does (see
[Concurrency and claim](#concurrency-and-claim)). Domain exceptions are ordinary control flow. Domain events are published by `UnitOfWork` only after
the commit.

If a use case runs inside a transaction opened by your application, the package transaction becomes a nested one (a
savepoint when the platform supports it): its changes become durable only when your outer transaction commits, but the
domain events are published when the nested transaction returns, i.e. before that commit. Use the outbox
([integration-events.md](integration-events.md)) for events that must not outrun the commit.

## Concurrency and claim

Optimistic locking uses the native Doctrine version field: `Submission` has a `version` integer, mapped with
`version="true"`, incremented by every update. A concurrent write that loses the race raises Doctrine's
`OptimisticLockException` at flush, translated by the repository into the `InvariantViolation`
`submission.concurrent_modification`.

`claim(Submission $reclaimed, SubmissionState $expectedStoredState): bool` is the concurrency guard of the upload retry.
It is one conditional `UPDATE ... WHERE id = :id AND version = :read AND state = :expected` that writes the reclaimed
fields and bumps the version: exactly one worker wins (returns `true`); the others, or a claim of an instance changed by
another process since it was read, update zero rows and get `false`. It records no domain
event and never loads the aggregate; the managed copy is detached, so read the submission again to see the claimed row.

**A failed flush closes the entity manager.** When a flush fails (a concurrent insert hit the unique constraint, a lost
optimistic lock on a managed instance, a database error), the ORM closes the entity manager. The repository still
reports `submission.duplicate_external_reference`, `submission.duplicate_id` or `submission.concurrent_modification`,
and DQL reads and the conditional `UPDATE` of `claim()` still work on the healthy connection, but every later flush
fails with an "entity manager closed" error. A long-running process must reset the entity manager
(`ManagerRegistry::resetManager()`; a Messenger worker does it itself). The package never resets it.

A submission left in `uploading` (worker crash, lost answer) is never resent blindly: see
[ADR 0003](adr/0003-unknown-upload-outcome.md).

## Uniqueness and duplicates

| Code | Cause |
|---|---|
| `submission.duplicate_external_reference` | `(tenant_id, external_reference)` already exists. |
| `submission.duplicate_id` | The submission id already exists (primary key). |

`add()` checks both conditions first, then inserts inside a DBAL transaction. Nested in the transaction of the use case,
that transaction is a savepoint: if the insert still hits the unique constraint (a concurrent writer won the race), the
savepoint is rolled back, not the whole transaction. This matters on PostgreSQL, where a failed statement aborts the
enclosing transaction: the use case that re-reads the existing submission after a duplicate (the upload claim) still
works on a healthy connection (the failed flush has closed the entity manager: see above). The behaviour is covered by a test on SQLite.
