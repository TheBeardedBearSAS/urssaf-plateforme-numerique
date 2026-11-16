# Without the Symfony bundle

`TheBeardedBear\UrssafPlateformeNumerique\Standalone\UrssafPlateformeNumeriqueBuilder` builds every use case of the
package from a PSR-18 transport, a `CredentialsProvider`, a `PlatformResolver` and the `ApiConfiguration`; `build()`
returns `Standalone\UrssafPlateformeNumerique`, whose methods return the handlers:

- commands: `uploadIdentification()`, `uploadDeclaration()`, `syncSubmissionStatuses()`,
  `syncAllSubmissionStatuses()`, `refreshSubmission()`, `reconcileUnknownSubmissions()`,
  `resolveSubmissionOutcome()`, `relayOutbox()`, `purgeOutbox()`;
- queries: `getIdentification()`, `getDeclaration()`, `searchIdentifications()`, `searchDeclarations()`,
  `downloadReportFile()`, `getNewsFeed()`, `getSubmission()`, `findSubmissions()`, `getWorkflowOverview()`;
- the gateway itself: `gateway()`.

The same use cases as the Symfony bundle, wired the same way. Each accessor returns a new stateless handler.

## Quick start

The example needs a PSR-18 client and PSR-17 factories (here `nyholm/psr7` and Guzzle; any other pair works).
Credentials come from environment variables, and the platform identifier of each tenant from your own data.

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
        $file->stream()->copyTo($destination); // an open resource; the caller closes it
        fclose($destination);
        $file->stream()->close();
    }
}
```

The file identifiers of the reports (`globalReportRef`, `detailedReportRef`...) come from `getIdentification()` or
`getDeclaration()`.

Every `with...()` method returns a new builder:

| Method | Default | Use |
|---|---|---|
| `withRepository($submissions, $statistics, $transactions)` | in-memory | Your own persistence; the transaction manager makes the repository and the outbox atomic. |
| `withTokenStore(TokenStore)` | in-memory | A store shared by every process (`Psr6TokenStore`, `Psr16TokenStore`). |
| `withClock(Clock)` | UTC system clock | For instance `new SystemClock($psr20Clock)`. |
| `withIdGenerator(IdGenerator)` | random UUIDs | |
| `withEventPublisher(EventPublisher)` | none | Domain events after each commit, e.g. `new Psr14EventPublisher($dispatcher)`. |
| `withLogger(LoggerInterface)` | none | Logs of the HTTP gateway and of the event publication failures. |
| `withRateLimiter(RateLimiter)` | none | `SlidingWindowRateLimiter` to enforce a quota in the process. |
| `withPolicy(WorkflowPolicy)` | defaults | Poll intervals, upload attempts, page sizes, outbox retention... |
| `withOutbox(Outbox, IntegrationEventSink)` | no outbox | Integration events (see [integration-events.md](integration-events.md)); `relayOutbox()` needs it. |
| `withGateway(PlatformGateway)` | HTTP gateway | For instance the `Testing\SimulatedPlatformGateway` in tests ([testing.md](testing.md)). |

Schedule the polling and recovery use cases yourself (cron), like the console commands of the bundle:
`syncAllSubmissionStatuses()`, `reconcileUnknownSubmissions()` per tenant, and `relayOutbox()` with the outbox. Two
runs of the same use case must not overlap (use a lock of your own); the atomic claims keep them safe but wasteful.

## Manual wiring

The builder only calls public constructors: wire the classes yourself when you need another combination.

```php
$gateway = UrssafGatewayFactory::createWith($transport, $credentials, $tokenStore, $configuration, GatewayOptions::defaults()->withClock($clock));
$unitOfWork = new UnitOfWork($transactions, $eventPublisher, $ids, new PsrLoggerFailureReporter($logger), $outbox);

$uploader = new SubmissionUploader($submissions, $gateway, $platforms, $ids, $clock, $unitOfWork, $policy);
$uploadIdentification = new UploadIdentificationHandler($uploader);
$syncSubmissionStatuses = new SyncSubmissionStatusesHandler($submissions, $gateway, $clock, $unitOfWork, $policy);
```
