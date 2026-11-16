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

The simulator proves your code, not the URSSAF contract. The contract tests of this package (`tests/Contract`, group
`contract`, `make test-contract`) call the real platforms with credentials taken from environment variables. They are
never part of the default run (`make ci`, `make test`), nor of the coverage and mutation gates; without credentials each
test is skipped. No real data is used: the identifiers are yours, the uploaded file and its name are fictitious and
generated per run.

| Variable | Role |
|---|---|
| `URSSAF_SANDBOX_CLIENT_ID`, `URSSAF_SANDBOX_CLIENT_SECRET` | Required: credentials of the "Echanges reels" sandbox (`api-edi`). |
| `URSSAF_SANDBOX_PLATFORM_ID` | Platform id of that sandbox; the tests that need a platform are skipped, with an explicit message, without it. |
| `URSSAF_SANDBOX_BASE_URI` | Optional host override of the sandbox (the base path and scope still follow the target). |
| `URSSAF_TEST_API_CLIENT_ID`, `URSSAF_TEST_API_CLIENT_SECRET`, `URSSAF_TEST_API_PLATFORM_ID` | Optional: the technical test API ("Bac a sable", served from `api.urssaf.fr`); its tests are skipped when missing. |
| `URSSAF_SANDBOX_UPLOAD` | `1` enables the upload test (off by default). |

What they check:

- a token is obtained on the sandbox host (this settles the open point about the token host: a failure is only
  reported, there is no automatic retry against `https://api.urssaf.fr`);
- the identification and declaration searches answer a page whose `UCN-*` pagination headers are read; only types are
  asserted, any content is accepted;
- `get` of an unknown but well-formed `urssafRef` (a fictitious UUID) answers `UrssafResourceNotFound`; a
  `UrssafRequestRejected` (400 is only declared generically) does not fail the test, which is reported as incomplete
  with the HTTP status and the raw error codes in its message;
- the news feed answers a page on the sandbox; on the test API the gateway refuses it locally
  (`OperationNotSupported`) and a raw call to `/news-feed` answers 404 (`UnexpectedUrssafResponse`);
- opt-in upload: a fictitious `.xml.gz` is sent (its processing is expected to be rejected asynchronously), the
  `urssafRef` is returned, the search (no keyword, newest first, pages of 500, like the reconciliation service) then lists a
  submission whose file name equals the uploaded name (the assumption of [ADR 0003](adr/0003-unknown-upload-outcome.md)), and `get` answers `IN_PROCESS`, `ACCEPTED` or `REJECTED` with a
  parsed `lastUpdateDate`.

```sh
export URSSAF_SANDBOX_CLIENT_ID=... URSSAF_SANDBOX_CLIENT_SECRET=... URSSAF_SANDBOX_PLATFORM_ID=...
make test-contract
```

In GitHub Actions the `contract` job is manual (Actions > CI > Run workflow, `contract` checked) and reads the secrets
(client ids and secrets) and the variables (platform ids, base URI) of the `urssaf-sandbox` environment. The upload test
writes to the sandbox, so it runs only when `contract_upload` is also checked for that run (the job then sets
`URSSAF_SANDBOX_UPLOAD=1`; the variable is never read from the environment of the repository). Locally,
`make test-contract` still reads `URSSAF_SANDBOX_UPLOAD`.
