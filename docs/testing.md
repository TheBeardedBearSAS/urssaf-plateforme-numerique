# Testing a host application

The package ships `TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway`, an in-memory URSSAF
platform: it implements the gateway port (`Application\Port\Urssaf\PlatformGateway`), keeps the uploaded files per
tenant and platform, and answers the nine operations like the real API, without any network call. Use it to test your
integration (controllers, listeners, crons, Messenger handlers) end to end; keep the live platform for the contract
tests.

It must never be used outside tests: nothing it accepts is sent to URSSAF.

## Default behaviour

| Operation | Default answer |
|---|---|
| `uploadPlatformIdentification`, `uploadPlatformDeclaration` | Accepted: a new URSSAF reference is returned, `simulated-1`, `simulated-2`... (the count of accepted uploads). The file name, kind, declaration type and withholding period are remembered; the period is read from the file name (`YYYYMM` or `YYYY-MM`, for example `DPDE_202505_24001_000_20250428150823.xml.gz`), else it is the month of the clock. The file content is not read. |
| `getPlatformIdentification`, `getPlatformDeclaration` | Each read of a submission returns the next step of its processing status sequence, the last step is repeated. Default sequence: `IN_PROCESS`, `ACCEPTED`. A declaration also carries its payment status sequence (default: `NOT_INITIATED`). The treatment date appears once the status is final. A reference unknown to the tenant and platform, or of the other kind, is refused as not found (HTTP 404). |
| `searchPlatformIdentification`, `searchPlatformDeclaration` | The submissions of the tenant and platform, filtered by `type`, by the `search` keyword (contained, whatever the case, in the file name) and by the `start_date` / `end_date` bounds (at least one withholding period on or after / on or before the month of the date). Sorted on `period` and `lastUpdateDate` as requested (several sorts break the ties of the previous ones); any other field keeps the upload order (the sort uses the update instants from before the read, which then moves the instants of the returned page to the clock). Paginated (page 1, 20 items by default). Each submission of the returned page is read once, like a get. |
| `downloadIdentificationRelatedFile`, `downloadDeclarationRelatedFile` | The content scripted for the file id (see below), typed `application/octet-stream` and without `Content-Disposition`, as on the real API. A file id without scripted content, or a submission unknown to the tenant and platform, is refused as not found. |
| `getPlatformNewsFeed` | The scripted items, paginated. Refused with `OperationNotSupported` when the simulator is built for `Environment::TestApi`, like the real test API (ADR 0001). |

Every submission lists a global report (`<reference>-global-report`) and, once it is accepted, a detailed report
(`<reference>-detailed-report`).

## Scripting

```php
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;

$urssaf->progressThrough(FileStatus::InProcess, FileStatus::Rejected);                  // files uploaded from now on
$urssaf->progressPaymentThrough(PaymentStatus::Processing, PaymentStatus::Processed);   // declarations uploaded from now on
$urssaf->rejectNextUpload('ERR_EXAMPLE_CODE');                                           // next upload refused (HTTP 400)
$urssaf->reportContent('simulated-1-global-report', 'fictitious report content');        // downloadable report
$urssaf->newsItems(NewsItem::of('news-1', 'A fictitious news.', null));                  // served by the news feed
$urssaf->failNext(Operation::GetDeclaration, UrssafUnavailable::withStatus(Operation::GetDeclaration, 503, 30));
```

Sequences apply to the files uploaded afterwards. `failNext()` queues a failure per operation, thrown in order by the
next calls of that operation (the status sequences are not moved by a failed read).

Inspection: `calls()` returns every operation received, in order, failed ones included.

## Without Symfony

Plug the simulator into the standalone builder ([standalone.md](standalone.md)):

```php
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway;

$clock = new FrozenClock(new DateTimeImmutable('2026-03-10T08:00:00Z'));
$urssaf = new SimulatedPlatformGateway($clock);                 // Environment::Sandbox by default
$package = UrssafPlateformeNumeriqueBuilder::create($transport, $credentials, $platforms, $configuration)
    ->withClock($clock)
    ->withGateway($urssaf)
    ->build();
```

Pass the same clock to the builder and to the simulator: the dates of the submissions follow it.

## With the Symfony bundle

Enable the simulator in the test environment only:

```yaml
# config/packages/test/urssaf_plateforme_numerique.yaml
urssaf_plateforme_numerique:
    gateway: { simulated: true }
```

The gateway port is then the simulator, available as the public service
`TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway` to script the scenario. The container
refuses `gateway.simulated: true` with `environment: production`.

```php
final class IdentificationAcceptedTest extends KernelTestCase
{
    public function testTheIdentificationIsAcceptedAfterTwoPolls(): void
    {
        self::bootKernel();
        $urssaf = self::getContainer()->get(SimulatedPlatformGateway::class);
        $urssaf->progressThrough(FileStatus::InProcess, FileStatus::InProcess, FileStatus::Accepted);

        // ... run urssaf:pn:identification:upload, then urssaf:pn:submissions:sync three times ...

        self::assertSame(Operation::UploadIdentification, $urssaf->calls()[0]);
    }
}
```

The simulator lives in the container: it is reset with the kernel (each `bootKernel()`), and shared by the commands and
handlers of the same kernel.

## Contract tests against the real platform

The simulator proves your code, not the URSSAF contract. The contract tests of this package (`make test-contract`) call
the real platform with credentials taken from environment variables; they are never part of the default test run, and
their setup is described with them.
