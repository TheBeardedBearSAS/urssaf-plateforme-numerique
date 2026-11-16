<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Standalone;

use DateTimeImmutable;
use Http\Mock\Client;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RefreshSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ResolveSubmissionOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncAllSubmissionStatuses;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadDeclaration;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetDeclaration;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchDeclarations;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchIdentifications;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\InMemoryFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Standalone\Support\StandaloneSetup;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\AdjustableClock;

/**
 * The package wired on the simulated platform, as a host application would in its own tests.
 */
final class UrssafPlateformeNumeriqueSimulatorTest extends TestCase
{
    private const NOW = '2025-04-28T20:00:00+00:00';

    public function testTheWholeWorkflowRunsOnTheSimulator(): void
    {
        $clock = new AdjustableClock(new DateTimeImmutable(self::NOW));
        $urssaf = new SimulatedPlatformGateway($clock);
        $package = StandaloneSetup::builder(new Client())->withClock($clock)->withGateway($urssaf)->build();
        $tenant = TenantId::fromString(StandaloneSetup::TENANT);
        $declarationFile = 'DPDE_202505_24001_000_20250428150823.xml.gz';

        $uploaded = $package->uploadDeclaration()(UploadDeclaration::of(
            $tenant,
            ExternalReference::fromString('host-ref-2'),
            DeclarationType::Declaration,
            InMemoryFileSource::of(FileName::fromString($declarationFile), 'fictitious-bytes'),
        ));
        static::assertSame(SubmissionState::InProcess, $uploaded->state);

        $clock->advanceSeconds(660);
        $package->syncAllSubmissionStatuses()(SyncAllSubmissionStatuses::forTenants(null, $tenant));
        static::assertSame('in_process', $package->getSubmission()(new GetSubmission($tenant, $uploaded->submissionId))->state);
        $clock->advanceSeconds(660);
        $package->syncAllSubmissionStatuses()(SyncAllSubmissionStatuses::forTenants(null, $tenant));
        $view = $package->getSubmission()(new GetSubmission($tenant, $uploaded->submissionId));
        static::assertSame('accepted', $view->state);
        static::assertSame('simulated-1', $view->urssaf->urssafRef);

        $reference = UrssafReference::fromString('simulated-1');
        $declaration = $package->getDeclaration()(new GetDeclaration($tenant, $reference));
        static::assertSame($declarationFile, $declaration->declarationFileName());
        static::assertSame('simulated-1', $declaration->urssafRef()?->value());
        $found = $package->searchDeclarations()(new SearchDeclarations($tenant, SubmissionSearch::all()));
        static::assertSame([$declarationFile], array_map(static fn(DeclarationSubmission $item): ?string => $item->declarationFileName(), $found->items()));
        static::assertSame([], $package->searchIdentifications()(new SearchIdentifications($tenant, SubmissionSearch::all()))->items());
        static::assertSame('accepted', $package->refreshSubmission()(RefreshSubmission::of($tenant, $uploaded->submissionId))->state);

        $urssaf->failNext(Operation::UploadDeclaration, UrssafUnavailable::dueToTransportError(Operation::UploadDeclaration, RuntimeException::class));
        $unknown = $package->uploadDeclaration()(UploadDeclaration::of(
            $tenant,
            ExternalReference::fromString('host-ref-3'),
            DeclarationType::Declaration,
            InMemoryFileSource::of(FileName::fromString($declarationFile), 'fictitious-bytes'),
        ));
        static::assertSame(SubmissionState::OutcomeUnknown, $unknown->state);
        static::assertSame('not_received', $package->resolveSubmissionOutcome()(ResolveSubmissionOutcome::notReceived($tenant, $unknown->submissionId))->state);

        $urssaf->reportContent('simulated-1-global-report', 'fictitious report');
        $file = $package->downloadReportFile()(DownloadReportFile::declaration($tenant, $reference, FileReference::of('simulated-1-global-report', 'global-report')));
        static::assertSame('fictitious report', $file->stream()->read(100));
        $file->stream()->close();

        $urssaf->newsItems(NewsItem::of('news-1', 'A fictitious news.', null));
        $news = $package->getNewsFeed()(new GetNewsFeed($tenant, PageRequest::firstPage()));
        static::assertSame(['news-1'], array_map(static fn(NewsItem $item): ?string => $item->idNews(), $news->items()));

        static::assertSame([
            Operation::UploadDeclaration,
            Operation::GetDeclaration,
            Operation::GetDeclaration,
            Operation::GetDeclaration,
            Operation::SearchDeclarations,
            Operation::SearchIdentifications,
            Operation::GetDeclaration,
            Operation::UploadDeclaration,
            Operation::DownloadDeclarationFile,
            Operation::GetNewsFeed,
        ], $urssaf->calls());
    }

    public function testAnIdentificationUploadedOnTheSimulatorIsReadBack(): void
    {
        $clock = new AdjustableClock(new DateTimeImmutable(self::NOW));
        $urssaf = new SimulatedPlatformGateway($clock);
        $package = StandaloneSetup::builder(new Client())->withClock($clock)->withGateway($urssaf)->build();
        $tenant = TenantId::fromString(StandaloneSetup::TENANT);
        $identificationFile = 'DPID_202506_24001_000_20250428150823.xml.gz';

        $uploaded = $package->uploadIdentification()(UploadIdentification::of(
            $tenant,
            ExternalReference::fromString('host-ref-1'),
            InMemoryFileSource::of(FileName::fromString($identificationFile), 'fictitious-bytes'),
        ));
        $view = $package->getSubmission()(new GetSubmission($tenant, $uploaded->submissionId));
        $identification = $package->getIdentification()(new GetIdentification($tenant, UrssafReference::fromString('simulated-1')));

        static::assertSame('simulated-1', $view->urssaf->urssafRef);
        static::assertSame($identificationFile, $identification->identificationFileName());
    }
}
