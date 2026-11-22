# URSSAF "API portail Plateforme numérique" for PHP

<!-- the-bearded-bear:badges:start -->
[![CI](https://github.com/TheBeardedBearSAS/urssaf-plateforme-numerique/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/TheBeardedBearSAS/urssaf-plateforme-numerique/actions/workflows/ci.yml?query=branch%3Amain) [![Coverage](https://codecov.io/gh/TheBeardedBearSAS/urssaf-plateforme-numerique/branch/main/graph/badge.svg)](https://app.codecov.io/gh/TheBeardedBearSAS/urssaf-plateforme-numerique) [![Mutation score](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FTheBeardedBearSAS%2Furssaf-plateforme-numerique%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/TheBeardedBearSAS/urssaf-plateforme-numerique/main) [![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-plateforme-numerique&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=TheBeardedBearSAS_urssaf-plateforme-numerique) [![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-plateforme-numerique&metric=sqale_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-plateforme-numerique) [![Reliability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-plateforme-numerique&metric=reliability_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-plateforme-numerique) [![Security](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-plateforme-numerique&metric=security_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-plateforme-numerique) [![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/TheBeardedBearSAS/urssaf-plateforme-numerique/badge)](https://scorecard.dev/viewer/?uri=github.com/TheBeardedBearSAS/urssaf-plateforme-numerique) [![OpenSSF Best Practices](https://www.bestpractices.dev/projects/16844/badge)](https://www.bestpractices.dev/projects/16844)
[![Packagist version](https://img.shields.io/packagist/v/thebeardedbear/urssaf-plateforme-numerique)](https://packagist.org/packages/thebeardedbear/urssaf-plateforme-numerique) [![PHP version](https://img.shields.io/packagist/dependency-v/thebeardedbear/urssaf-plateforme-numerique/php)](https://packagist.org/packages/thebeardedbear/urssaf-plateforme-numerique) [![Downloads](https://img.shields.io/packagist/dt/thebeardedbear/urssaf-plateforme-numerique)](https://packagist.org/packages/thebeardedbear/urssaf-plateforme-numerique/stats) [![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](phpstan.neon.dist) [![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
<!-- the-bearded-bear:badges:end -->

`thebeardedbear/urssaf-plateforme-numerique` — client, domain model and Symfony integration for the URSSAF "portail
Plateforme numérique" APIs, used by digital platforms for the withholding at source of the social contributions of
auto-entrepreneurs: monthly identification files, declaration / regularisation files, processing and payment status,
report downloads and notification feed. One package covers two catalogue entries sharing the same resources:
**Échanges réels** and **Bac à sable** (technical test API).

Namespace: `TheBeardedBear\UrssafPlateformeNumerique`.

Developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).

> [!IMPORTANT]
> **Unofficial package.** This project is an independent open-source implementation of a public API. It is not
> developed, endorsed, certified or supported by URSSAF, and it is not affiliated with URSSAF in any way. "URSSAF" is
> used only to name the API this package talks to. Using this package does not replace the URSSAF habilitation
> process; the URSSAF documentation remains the authority on the API, and you remain responsible for the data your
> application sends to URSSAF.

**Status: 0.1.0 released; sandbox contract run still to do (see [docs/roadmap.md](docs/roadmap.md)).** The nine
operations, the submission workflow, the standalone builder, the Symfony bundle and the Doctrine bridge are in place;
the open points of the API reference stay open until URSSAF publishes them or the sandbox contract run settles them.
See the architecture decision records in [docs/adr/](docs/adr/README.md).

## Scope

| Operation | Endpoint | Échanges réels | Bac à sable |
|---|---|---|---|
| Upload an identification file | `POST /platforms/{platformId}/identifications/upload` | yes | yes |
| Get an identification submission | `GET /platforms/{platformId}/identifications/{urssafRef}` | yes | yes |
| Search identification submissions | `GET /platforms/{platformId}/identifications` | yes | yes |
| Download an identification result file | `GET /platforms/{platformId}/identifications/{urssafRef}/file/{fileRef}` | yes | yes |
| Upload a declaration / regularisation file | `POST /platforms/{platformId}/declarations/upload?type=…` | yes | yes |
| Get a declaration submission | `GET /platforms/{platformId}/declarations/{urssafRef}` | yes | yes |
| Search declaration submissions | `GET /platforms/{platformId}/declarations` | yes | yes |
| Download a declaration report file | `GET /platforms/{platformId}/declarations/{urssafRef}/file/{fileRef}` | yes | yes |
| Notification feed | `GET /platforms/{platformId}/news-feed` | yes | no |

OAuth2 client credentials (prior registration on the "portail Plateforme numérique" required):

| Target | Base URL | Scope |
|---|---|---|
| Échanges réels, production | `https://api.urssaf.fr/api-odspn-be-platforms/v1` | `ods.pn.platforms` |
| Échanges réels, sandbox | `https://api-edi.urssaf.fr/api-odspn-be-platforms/v1` | `ods.pn.platforms` |
| Bac à sable (test API) | `https://api.urssaf.fr/api-odspn-be-platforms-sandbox/v1` | `ods.pn.platforms.sandbox` |

## Documentation

- [docs/api-reference.md](docs/api-reference.md) — technical reference (single source of truth for the package).
- [docs/roadmap.md](docs/roadmap.md) — implementation status and what remains; [ROADMAP.md](ROADMAP.md) — intended direction for the next twelve months.
- [docs/security/](docs/security/assurance-case.md) — security assurance case and security review.
- [docs/workflow.md](docs/workflow.md) — submission lifecycle, re-entry, polling, reconciliation of unknown uploads, declaration guard.
- [docs/integration-events.md](docs/integration-events.md) — public integration events (`urssaf.pn.*`) and the outbox.
- [docs/doctrine.md](docs/doctrine.md) — Doctrine persistence: tables, dedicated entity manager, concurrency, migrations.
- [docs/standalone.md](docs/standalone.md) — wiring without the Symfony bundle: builder options, scheduling, manual wiring.
- [docs/symfony.md](docs/symfony.md) — Symfony bundle: configuration reference, console commands, Messenger, Scheduler, locks, translations.
- [docs/testing.md](docs/testing.md) — testing a host application with the in-memory simulated gateway.
- [docs/adr/](docs/adr/README.md) — architecture decision records.
- [docs/openapi/](docs/openapi/) — official OpenAPI contract(s) from the URSSAF API portal.
- `docs/reference/` — official URSSAF documentation: not versioned (URSSAF documents), download it from the links in [docs/api-reference.md](docs/api-reference.md) into this local, git-ignored folder.
- Catalogue pages: https://portailapi.urssaf.fr/fr/catalogue-api/prd/portail-pn-echanges-reels and
  https://portailapi.urssaf.fr/fr/catalogue-api/prd/portail-pn-bac-a-sable

## Installation

```bash
composer require thebeardedbear/urssaf-plateforme-numerique
```

The package depends on PSR interfaces only: install a PSR-18 HTTP client and PSR-17 factories, for instance
`composer require nyholm/psr7 guzzlehttp/guzzle` (or `symfony/http-client` and `nyholm/psr7`; with the Symfony bundle
the default client is a dedicated Symfony HttpClient). With Symfony Flex, the recipe of the package registers the
bundle, copies a commented `config/packages/urssaf_plateforme_numerique.yaml` (the simulated URSSAF platform in the
`test` environment) and adds `URSSAF_PN_ENV`, `URSSAF_PN_CLIENT_ID`, `URSSAF_PN_CLIENT_SECRET` and
`URSSAF_PN_PLATFORM_ID` to `.env`. The recipe is submitted to
[symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) ([symfony/recipes-contrib#2123](https://github.com/symfony/recipes-contrib/pull/2123)) and not merged yet: until then, do these
steps by hand (Quick start B below), or serve the recipe from your own Flex endpoint built by `bin/build-flex-endpoint` (see
[docs/symfony.md](docs/symfony.md#symfony-flex-recipe)).

With Doctrine, generate the tables of the package (it ships no migration, see
[docs/doctrine.md](docs/doctrine.md#migrations)) and check the installation:

```bash
php bin/console doctrine:migrations:diff --em=urssaf_pn && php bin/console doctrine:migrations:migrate
php bin/console urssaf:pn:check
```

## Architecture

Hexagonal / DDD, the same as `thebeardedbear/urssaf-tiers-prestations` ([ADR 0000](docs/adr/0000-reference-architecture.md)).
Layers are enforced by Deptrac (`deptrac.yaml`): the Domain depends on nothing, the Application on the Domain and the
Contract, the Infrastructure on the Application and PSR only.

```
            +-----------------------------------------------+
            | Bridge (Symfony, Doctrine)  - framework glue  |
            +----------------------+------------------------+
                                   |
            +----------------------v------------------------+
            | Infrastructure - HTTP gateway, OAuth2, wire   |
            | encoding, token stores, clock, ids, in-memory |
            +----------------------+------------------------+
                                   | implements ports
            +----------------------v------------------------+
            | Application - commands, queries, handlers,    |
            | ports (PlatformGateway, repositories...), DTOs|
            +----------------------+------------------------+
                                   |
            +----------------------v------------------------+
            | Domain - Submission aggregate, value objects, |
            | rules (pure PHP, no PSR, no framework)        |
            +-----------------------------------------------+
```

- **Domain**: the `Submission` aggregate and its lifecycle, value objects (periods, references, file names,
  fingerprints), the live read models of identifications, declarations and news items, search criteria, domain events.
- **Contract**: public, versioned integration events (`urssaf.pn.*`) relayed through the outbox.
- **Application**: one `final readonly` handler per use case, ports implemented by the outer layers.
- **Infrastructure**: `UrssafHttpGateway` (implements `PlatformGateway`), OAuth2 token provider, multipart and
  download handling, adapters.
- **Standalone** / **Testing**: framework-free builder and an in-memory simulated platform for host test suites.
- **Bridge**: `Bridge\Symfony` (bundle, commands, Messenger, Lock, Scheduler) and `Bridge\Doctrine` (XML mapping, DBAL
  types, repository with an atomic claim, optimistic locking, outbox, statistics; no `userId` / `userEmail` column).

Decisions: [docs/adr/](docs/adr/README.md).

## Quick start A: Standalone (framework-agnostic)

The package sends the files you give it (it never generates, compresses nor validates the XML) and follows their
asynchronous processing. This example needs a PSR-18 client and PSR-17 factories (here `nyholm/psr7` and Guzzle).
Credentials come from environment variables, the platform identifier of each tenant from your own data.

```php
use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatuses;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\LocalFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Platform\InMemoryPlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafPlateformeNumerique\Standalone\UrssafPlateformeNumeriqueBuilder;

$factory = new Psr17Factory();
$tenant = TenantId::fromString('tenant-1');

$package = UrssafPlateformeNumeriqueBuilder::create(
    new HttpTransport(new Client(), $factory, $factory),
    new InMemoryCredentialsProvider([
        'tenant-1' => Credentials::of((string) getenv('URSSAF_CLIENT_ID'), (string) getenv('URSSAF_CLIENT_SECRET')),
    ]),
    new InMemoryPlatformResolver(['tenant-1' => PlatformId::fromString('platform-1')]),
    ApiConfiguration::forEnvironment(Environment::Sandbox),
)->build();

// 1. Upload: the submission is recorded locally ("uploading"), sent, then "in_process".
$report = $package->uploadIdentification()(UploadIdentification::of(
    $tenant,
    ExternalReference::fromString('host-ref-1'),
    LocalFileSource::fromPath('/path/to/DPID_202505_24001_000_20250428150823.xml.gz'),
));

// 2. Poll (from a cron job): every submission still in process is read at URSSAF.
$package->syncSubmissionStatuses()(SyncSubmissionStatuses::forTenant($tenant, null));

// 3. Read the local record, then download a report once it is accepted.
$submission = $package->getSubmission()(new GetSubmission($tenant, $report->submissionId));
if ($submission->state === 'accepted' && $submission->urssaf->urssafRef !== null) {
    $reference = UrssafReference::fromString($submission->urssaf->urssafRef);
    $identification = $package->getIdentification()(new GetIdentification($tenant, $reference));
    $globalReport = $identification->reportFiles()->globalReport();
    if ($globalReport !== null) {
        $file = $package->downloadReportFile()(DownloadReportFile::identification($tenant, $reference, $globalReport));
        $destination = fopen('report.bin', 'wb');
        if ($destination !== false) {
            $file->stream()->copyTo($destination); // an open resource; the caller closes it
            fclose($destination);
        }
        $file->stream()->close();
    }
}
```

The other use cases are one accessor away (`uploadDeclaration()`, `searchDeclarations()`, `getNewsFeed()`,
`reconcileUnknownSubmissions()`...). Every option of the builder, the scheduling and the manual wiring are described in
[docs/standalone.md](docs/standalone.md); the lifecycle of a submission is in [docs/workflow.md](docs/workflow.md); test
your integration with the simulator ([docs/testing.md](docs/testing.md)).

## Quick start B: Symfony

Once the recipe is merged in symfony/recipes-contrib ([symfony/recipes-contrib#2123](https://github.com/symfony/recipes-contrib/pull/2123)) and the application allows contrib recipes
(`composer config extra.symfony.allow-contrib true`), Symfony Flex will register the bundle, copy the configuration
and add the variables to `.env`:

```sh
composer require thebeardedbear/urssaf-plateforme-numerique
```

Without the recipe, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\UrssafPlateformeNumeriqueBundle::class => ['all' => true],
];
```

Configure it in `config/packages/urssaf_plateforme_numerique.yaml` (every option is described in
[docs/symfony.md](docs/symfony.md)):

```yaml
framework:
    cache:
        pools:
            urssaf_pn.token_cache:              # the access tokens, shared by every process (web, workers, cron)
                adapter: cache.adapter.redis
                provider: '%env(REDIS_DSN)%'

urssaf_plateforme_numerique:
    environment: '%env(URSSAF_PN_ENV)%'         # sandbox | test_api | production
    credentials:
        default:                                # one entry per tenant (platform account)
            client_id: '%env(URSSAF_PN_CLIENT_ID)%'
            client_secret: '%env(URSSAF_PN_CLIENT_SECRET)%'
            platform_id: '%env(URSSAF_PN_PLATFORM_ID)%'
    token_store: { cache_pool: urssaf_pn.token_cache }
    persistence: { driver: doctrine, entity_manager: urssaf_pn }   # a dedicated entity manager (docs/doctrine.md)

when@test:
    urssaf_plateforme_numerique:
        gateway: { simulated: true }            # the in-memory URSSAF platform: nothing is sent to URSSAF
```

and set the variables in `.env.local` (never commit the secret):

```dotenv
URSSAF_PN_ENV=sandbox
URSSAF_PN_CLIENT_ID=
URSSAF_PN_CLIENT_SECRET=
URSSAF_PN_PLATFORM_ID=
```

The use case handlers are public services, ready to be injected:

```php
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\LocalFileSource;

final class IdentificationController
{
    public function __construct(private readonly UploadIdentificationHandler $uploadIdentification) {}

    #[Route('/identifications/{reference}', methods: ['PUT'])]
    public function upload(string $reference): JsonResponse
    {
        $report = ($this->uploadIdentification)(UploadIdentification::of(
            TenantId::fromString('default'),
            ExternalReference::fromString($reference),
            LocalFileSource::fromPath('/path/to/DPID_202505_24001_000_20250428150823.xml.gz'),
        ));

        return new JsonResponse(['outcome' => $report->outcome->value, 'state' => $report->state->value]);
    }
}
```

Check the installation, then run the polling and recovery commands from cron, or let the Symfony Scheduler do it
(`scheduler: { enabled: true }`, then `messenger:consume scheduler_urssaf_pn`):

```sh
php bin/console urssaf:pn:check
php bin/console urssaf:pn:identification:upload var/DPID_202505_24001_000_20250428150823.xml.gz --external-reference=host-ref-1
php bin/console urssaf:pn:submissions:sync
php bin/console urssaf:pn:submissions:reconcile
```

Create the tables with your migrations (the package ships none): `php bin/console doctrine:migrations:diff --em=urssaf_pn`
(the dedicated entity manager configured above) then `doctrine:migrations:migrate` ([docs/doctrine.md](docs/doctrine.md)). Without Doctrine, the `in_memory` persistence
loses every record when the process ends: give your own repository (`custom` driver), see
[docs/symfony.md](docs/symfony.md).

## Requirements

PHP 8.2+. Symfony 6.4 / 7.x for the bundle (optional). Doctrine persistence (optional): `doctrine/orm` 3.5.3+ and `doctrine/dbal` 4.0+. Development only needs Docker: every `make` target runs in a container.

## Development

```sh
make install     # build the image, install dependencies
make ci          # full quality gate: cs, rector, phpstan, mago, deptrac, composer checks, hadolint, guard, coverage, infection
make test        # unit + functional tests
make coverage-branches # branch coverage (Xdebug, slow), at least 96 % enforced
make bench       # benchmarks (PHPBench, tests/Benchmark)
make help        # every target
```

`make ci PHP_VERSION=8.4` runs the gate on another PHP version.

Once the repository is public, `main` publishes its quality proofs, linked from the badges at the top of this file: the
coverage report on Codecov, the full mutation report on the Stryker Dashboard, the SonarQube Cloud quality gate and the
OpenSSF Scorecard. The benchmarks (`tests/Benchmark`, PHPBench in `tools/phpbench`, out of the package dependencies)
run on every pull request against the base commit, and the job fails on a regression of more than 20 %.

### Contract tests against the sandbox

```sh
export URSSAF_SANDBOX_CLIENT_ID=... URSSAF_SANDBOX_CLIENT_SECRET=... URSSAF_SANDBOX_PLATFORM_ID=...
# optional: URSSAF_SANDBOX_BASE_URI (host override), URSSAF_SANDBOX_UPLOAD=1 (opt-in upload of a fictitious file),
#           URSSAF_TEST_API_CLIENT_ID, URSSAF_TEST_API_CLIENT_SECRET, URSSAF_TEST_API_PLATFORM_ID (technical test API)
make test-contract
```

These tests (`tests/Contract`, group `contract`) call the real "Echanges reels" sandbox (`api-edi`) and, when its
variables are set, the "Bac a sable" test API. They are skipped when the credentials are missing (a test that needs a
platform id is skipped without it), and never run by `make ci`. The upload test is off unless `URSSAF_SANDBOX_UPLOAD=1`.
The GitHub `contract` job is manual (Actions > CI > Run workflow, `contract` checked): it reads the secrets and
variables of the `urssaf-sandbox` environment with the same names, except the upload: it runs only when the
`contract_upload` box is checked for that run (the job sets `URSSAF_SANDBOX_UPLOAD=1` for it; no variable of that name
is read). See [docs/testing.md](docs/testing.md).

## Versioning

Semantic Versioning. `SpecVersion::CURRENT` holds the version of the official URSSAF documentation implemented.

<!-- the-bearded-bear:start -->
## Other URSSAF packages

The same architecture, quality gate and conventions, one package per URSSAF API (overview: [the-bearded-bear.com/open-source/urssaf](https://the-bearded-bear.com/open-source/urssaf)):

| API | Package | Status |
|---|---|---|
| Tiers de prestations (immediate tax-credit advance) | [`thebeardedbear/urssaf-tiers-prestations`](https://github.com/TheBeardedBearSAS/urssaf-tiers-prestations) | Released (Packagist) |
| Attestations Vérification Code Sécurité (certificate verification) | [`thebeardedbear/urssaf-attestations-verification-code-securite`](https://github.com/TheBeardedBearSAS/urssaf-attestations-verification-code-securite) | Released (Packagist) |
| DPAE (pre-employment declaration) | [`thebeardedbear/urssaf-dpae`](https://github.com/TheBeardedBearSAS/urssaf-dpae) | Coming soon |
| Mobilité internationale (posting of workers abroad) | [`thebeardedbear/urssaf-mobilite-internationale`](https://github.com/TheBeardedBearSAS/urssaf-mobilite-internationale) | Released (Packagist) |
| Tierce Déclaration auto-entrepreneur | [`thebeardedbear/urssaf-tierce-declaration-auto-entrepreneur`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-auto-entrepreneur) | Released (Packagist) |
| Tierce Déclaration CESU | [`thebeardedbear/urssaf-tierce-declaration-cesu`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu) | Released (Packagist) |
| Tierce Déclaration PAJE | [`thebeardedbear/urssaf-tierce-declaration-paje`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje) | Released (Packagist) |

## Professional support

This package is developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).
If you need help with an URSSAF integration, The Bearded Bear can help with:

- the URSSAF habilitation and homologation process (subscription, sandbox tests, move to production);
- integrating the package into your application (Symfony, Doctrine or framework-free);
- support with a guaranteed response time, including the follow-up of URSSAF API changes;
- custom developments on top of the package.

Project page and integration offer: [the-bearded-bear.com/open-source/urssaf-plateforme-numerique](https://the-bearded-bear.com/open-source/urssaf-plateforme-numerique).
<!-- the-bearded-bear:end -->

## Contributing and security

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md) and the [Code of Conduct](CODE_OF_CONDUCT.md).
The project's governance is described in [GOVERNANCE.md](GOVERNANCE.md) and its direction in [ROADMAP.md](ROADMAP.md).
Security design and review: [assurance case](docs/security/assurance-case.md) (threat model, trust boundaries, design
principles, countered weaknesses) and [security review](docs/security/security-review-2026-11.md). Report
vulnerabilities privately as described in [SECURITY.md](SECURITY.md), never in a public issue.

## License

MIT. See [LICENSE](LICENSE). The software is provided "as is", without warranty of any kind.

The API specification summarised in [docs/api-reference.md](docs/api-reference.md) belongs to URSSAF; see the sources
listed there.
