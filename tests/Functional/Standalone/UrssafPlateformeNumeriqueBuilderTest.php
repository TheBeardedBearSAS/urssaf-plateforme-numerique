<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Standalone;

use DateInterval;
use DateTimeImmutable;
use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissions;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncAllSubmissionStatuses;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatuses;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\FindSubmissions;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\InMemoryFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemorySubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Standalone\UrssafPlateformeNumerique;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Standalone\Support\StandaloneSetup;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FailingEventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FakePlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Journal;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\RecordingTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\SequentialIdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\AdjustableClock;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

final class UrssafPlateformeNumeriqueBuilderTest extends TestCase
{
    private const FILE = 'DPID_202505_24001_000_20250428150823.xml.gz';
    private const NOW = '2025-04-28T20:00:00+00:00';

    public function testEveryUseCaseOfThePackageIsAvailable(): void
    {
        $expected = [];
        foreach (self::handlerFiles() as $file) {
            $namespace = str_contains($file, '/Command/') ? 'Command' : 'Query';
            $expected[] = 'TheBeardedBear\\UrssafPlateformeNumerique\\Application\\' . $namespace . '\\' . basename($file, '.php');
        }
        $available = [];
        foreach ((new ReflectionClass(UrssafPlateformeNumerique::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if ($method->isConstructor() || !$type instanceof ReflectionNamedType || !str_ends_with($type->getName(), 'Handler')) {
                continue;
            }
            $available[] = $type->getName();
        }
        sort($available);
        sort($expected);

        static::assertNotSame([], $expected);
        static::assertSame($expected, $available);
    }

    public function testASubmissionIsUploadedPolledAcceptedAndItsReportDownloaded(): void
    {
        $http = new Client();
        $http->addResponse(new Response(200, [], StandaloneSetup::token()));
        $http->addResponse(new Response(200, [], WireFixtures::load('upload_response')));
        $http->addResponse(new Response(200, [], WireFixtures::load('identification_response_handbuilt')));
        $http->addResponse(new Response(200, ['Content-Type' => 'application/xml'], '<report/>'));
        $tokens = new InMemoryTokenStore();
        $outbox = new InMemoryOutbox();
        $sink = new RecordingIntegrationEventSink();
        $clock = new AdjustableClock(new DateTimeImmutable(self::NOW));
        $package = StandaloneSetup::builder($http)
            ->withTokenStore($tokens)
            ->withClock($clock)
            ->withIdGenerator(new SequentialIdGenerator())
            ->withOutbox($outbox, $sink)
            ->build();
        $tenant = TenantId::fromString(StandaloneSetup::TENANT);

        $report = $package->uploadIdentification()(UploadIdentification::of(
            $tenant,
            ExternalReference::fromString('host-ref-1'),
            InMemoryFileSource::of(FileName::fromString(self::FILE), 'fictitious-bytes'),
        ));

        static::assertSame(UploadOutcome::Uploaded, $report->outcome);
        static::assertSame(SubmissionState::InProcess, $report->state);
        $submissionId = $report->submissionId;
        static::assertSame('id-1', $submissionId->value());
        static::assertSame('in_process', $package->getSubmission()(new GetSubmission($tenant, $submissionId))->state);

        $clock->advanceSeconds(660);
        $synced = $package->syncAllSubmissionStatuses()(SyncAllSubmissionStatuses::forTenants(null, $tenant));
        static::assertSame(1, $synced->total());

        $view = $package->getSubmission()(new GetSubmission($tenant, $submissionId));
        static::assertSame('accepted', $view->state);
        static::assertSame('cbcf2700-110d-4abf-9835-a9b46e68d7c4', $view->urssaf->urssafRef);
        static::assertSame(['accepted'], array_map(static fn(SubmissionView $item): string => $item->state, $package->findSubmissions()(new FindSubmissions($tenant, SubmissionState::Accepted, null, 0))));

        $file = $package->downloadReportFile()(DownloadReportFile::identification(
            $tenant,
            UrssafReference::fromString('cbcf2700-110d-4abf-9835-a9b46e68d7c4'),
            FileReference::of('a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'global_report.xml'),
        ));
        static::assertSame('<report/>', $file->stream()->read(100));
        $file->stream()->close();
        static::assertSame(1, $package->getWorkflowOverview()(GetWorkflowOverview::allTenants())->tenants()[0]->states['accepted']);

        $relayed = $package->relayOutbox()(RelayOutbox::of(null));
        static::assertSame(2, $relayed->dispatched);
        static::assertCount($relayed->dispatched, $sink->deliveredIds());
        $clock->advanceSeconds(31 * 86_400);
        static::assertSame(2, $package->purgeOutbox()(PurgeOutbox::withPolicyRetention()));
        static::assertCount(4, $http->getRequests());
        static::assertSame(UrssafHttpGateway::class, $package->gateway()::class);
    }

    public function testTheBatchUseCasesRunOnAnEmptyStore(): void
    {
        $gateway = new FakePlatformGateway();
        $package = StandaloneSetup::builder(new Client())->withGateway($gateway)->build();
        $tenant = TenantId::fromString(StandaloneSetup::TENANT);

        static::assertSame([], $package->syncSubmissionStatuses()(SyncSubmissionStatuses::forTenant($tenant, null))->items());
        static::assertSame([], $package->syncAllSubmissionStatuses()(SyncAllSubmissionStatuses::forTenants(null, $tenant))->items());
        static::assertSame([], $package->reconcileUnknownSubmissions()(ReconcileUnknownSubmissions::forTenant($tenant, null))->items());
        static::assertSame([], $package->findSubmissions()(new FindSubmissions($tenant, null, null, 0)));
        static::assertSame([], $package->getWorkflowOverview()(GetWorkflowOverview::allTenants())->tenants());
        static::assertSame([], $gateway->recorded()->calls());
        static::assertSame($gateway, $package->gateway());
    }

    public function testThePolicyBoundsTheBatches(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(
            new SubmissionUploaded('e1', '2025-04-28T10:00:00Z', StandaloneSetup::TENANT, 'a', 'ref-a', 'identification', null, self::FILE, 'urssaf-a'),
            new SubmissionUploaded('e2', '2025-04-28T10:00:00Z', StandaloneSetup::TENANT, 'b', 'ref-b', 'identification', null, self::FILE, 'urssaf-b'),
        );
        $policy = new WorkflowPolicy(
            new DateInterval('PT10M'),
            new DateInterval('PT6H'),
            new DateInterval('P1Y'),
            5,
            new DateInterval('PT1H'),
            new DateInterval('P1D'),
            1,
            true,
            new DateInterval('P30D'),
            1,
            1,
        );

        $report = StandaloneSetup::builder(new Client())->withPolicy($policy)->withOutbox($outbox, new RecordingIntegrationEventSink())->build()->relayOutbox()(RelayOutbox::of(null));

        static::assertSame(1, $report->dispatched);
    }

    public function testTheHttpGatewayIsBuiltFromTheTransportAndItsOptions(): void
    {
        $http = new Client();
        $http->addResponse(new Response(200, [], StandaloneSetup::token()));
        $limited = StandaloneSetup::builder($http)->withRateLimiter(new RejectingRateLimiter())->build();

        $this->expectException(RateLimitExceeded::class);
        $limited->gateway()->newsFeed(TenantId::fromString(StandaloneSetup::TENANT), PlatformId::fromString('platform-1'), PageRequest::firstPage());
    }

    public function testTheLoggerReceivesTheEventPublicationFailures(): void
    {
        $logger = new CapturingLogger();
        $http = new Client();
        $http->addResponse(new Response(200, [], StandaloneSetup::token()));
        $http->addResponse(new Response(200, [], WireFixtures::load('upload_response')));
        $package = StandaloneSetup::builder($http)
            ->withLogger($logger)
            ->withEventPublisher(new FailingEventPublisher(new RuntimeException('listener down')))
            ->build();

        $package->uploadIdentification()(UploadIdentification::of(
            TenantId::fromString(StandaloneSetup::TENANT),
            ExternalReference::fromString('host-ref-1'),
            InMemoryFileSource::of(FileName::fromString(self::FILE), 'fictitious-bytes'),
        ));

        static::assertContains('Domain event publication failed after commit.', array_column($logger->records(), 'message'));
        foreach ($logger->records() as $record) {
            if ($record['message'] !== 'Domain event publication failed after commit.') {
                continue;
            }
            static::assertSame('error', $record['level']);
            static::assertSame('listener down', $record['context']['message']);
        }
    }

    public function testTheGivenRepositoryAndTransactionManagerAreUsed(): void
    {
        $journal = new Journal();
        $submissions = new InMemorySubmissionRepository();
        $http = new Client();
        $http->addResponse(new Response(200, [], StandaloneSetup::token()));
        $http->addResponse(new Response(200, [], WireFixtures::load('upload_response')));
        $package = StandaloneSetup::builder($http)
            ->withRepository($submissions, new InMemoryWorkflowStatistics($submissions), new RecordingTransactionManager($journal))
            ->build();

        $package->uploadIdentification()(UploadIdentification::of(
            TenantId::fromString(StandaloneSetup::TENANT),
            ExternalReference::fromString('host-ref-1'),
            InMemoryFileSource::of(FileName::fromString(self::FILE), 'fictitious-bytes'),
        ));

        static::assertCount(1, $submissions->all());
        static::assertContains('commit', $journal->entries());
    }

    public function testTheRelayNeedsASink(): void
    {
        $package = StandaloneSetup::builder(new Client())->build();

        try {
            $package->relayOutbox();
            static::fail('The relay should not be available.');
        } catch (LogicException $failure) {
            static::assertSame('No IntegrationEventSink: give the outbox and its sink (withOutbox()).', $failure->getMessage());
        }
    }

    public function testABuilderIsNeverModified(): void
    {
        $builder = StandaloneSetup::builder(new Client());
        $simulated = $builder->withGateway(new SimulatedPlatformGateway(new AdjustableClock(new DateTimeImmutable(self::NOW))));

        static::assertInstanceOf(UrssafHttpGateway::class, $builder->build()->gateway());
        static::assertInstanceOf(SimulatedPlatformGateway::class, $simulated->build()->gateway());
    }

    /**
     * @return list<string>
     */
    private static function handlerFiles(): array
    {
        $files = [];
        foreach (['Command', 'Query'] as $directory) {
            $matches = glob(__DIR__ . '/../../../src/Application/' . $directory . '/*Handler.php');
            static::assertIsArray($matches);
            array_push($files, ...$matches);
        }

        return $files;
    }
}
