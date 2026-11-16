<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;

/**
 * One method per behaviour of the simulator (reports and news feed).
 *
 * @mago-expect lint:too-many-methods
 */
final class SimulatedPlatformGatewayReportsAndNewsTest extends TestCase
{
    public function testAnIdentificationReportIsDownloadedWithItsScriptedContent(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $reference = SimulatorFixtures::uploadIdentification($urssaf);
        $urssaf->reportContent('simulated-1-global-report', 'fictitious report');

        $file = $urssaf->downloadIdentificationFile(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference, FileReference::of('simulated-1-global-report', 'global-report'));

        static::assertSame('fictitious report', self::contentOf($file));
        static::assertSame('application/octet-stream', $file->contentType());
        static::assertNull($file->contentDisposition());
        static::assertSame([Operation::UploadIdentification, Operation::DownloadIdentificationFile], $urssaf->calls());
    }

    public function testADeclarationReportIsDownloadedWithoutNameAndTheLastScriptedContentWins(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $reference = SimulatorFixtures::uploadDeclaration($urssaf);
        $urssaf->reportContent('report-1', 'first');
        $urssaf->reportContent('report-1', 'replaced');

        $file = $urssaf->downloadDeclarationFile(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference, FileReference::of('report-1', null));

        static::assertSame('replaced', self::contentOf($file));
        static::assertNull($file->contentDisposition());
        static::assertSame(Operation::DownloadDeclarationFile, $urssaf->calls()[1]);
    }

    public function testAReportWithoutScriptedContentIsNotFound(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $reference = SimulatorFixtures::uploadDeclaration($urssaf);

        $notFound = self::notFound(static fn(): DownloadedFile => $urssaf->downloadDeclarationFile(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference, FileReference::of('unscripted', null)));

        static::assertSame(Operation::DownloadDeclarationFile, $notFound->operation());
    }

    public function testASubmissionUnknownToTheTenantPlatformOrKindHasNoReportToDownload(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $identification = SimulatorFixtures::uploadIdentification($urssaf);
        $declaration = SimulatorFixtures::uploadDeclaration($urssaf);
        $urssaf->reportContent('report-1', 'content');
        $report = FileReference::of('report-1', null);
        $tenant = SimulatorFixtures::tenant();
        $platform = SimulatorFixtures::platform();

        static::assertSame(Operation::DownloadIdentificationFile, self::notFound(static fn(): DownloadedFile => $urssaf->downloadIdentificationFile($tenant, $platform, UrssafReference::fromString('simulated-9'), $report))->operation());
        static::assertSame(Operation::DownloadIdentificationFile, self::notFound(static fn(): DownloadedFile => $urssaf->downloadIdentificationFile(SimulatorFixtures::otherTenant(), $platform, $identification, $report))->operation());
        static::assertSame(Operation::DownloadIdentificationFile, self::notFound(static fn(): DownloadedFile => $urssaf->downloadIdentificationFile($tenant, $platform, $declaration, $report))->operation());
        static::assertSame(Operation::DownloadDeclarationFile, self::notFound(static fn(): DownloadedFile => $urssaf->downloadDeclarationFile($tenant, SimulatorFixtures::otherPlatform(), $declaration, $report))->operation());
        static::assertSame(Operation::DownloadDeclarationFile, self::notFound(static fn(): DownloadedFile => $urssaf->downloadDeclarationFile($tenant, $platform, $identification, $report))->operation());
        static::assertSame('content', self::contentOf($urssaf->downloadIdentificationFile($tenant, $platform, $identification, $report)));
    }

    public function testAQueuedFailureIsThrownByADownload(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $reference = SimulatorFixtures::uploadDeclaration($urssaf);
        $urssaf->failNext(Operation::DownloadDeclarationFile, new RuntimeException('down'));

        $this->expectExceptionMessage('down');

        $urssaf->downloadDeclarationFile(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference, FileReference::of('report-1', null));
    }

    public function testTheNewsFeedServesTheScriptedItemsPaginated(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $items = [
            NewsItem::of('news-1', 'First fictitious news.', null),
            NewsItem::of('news-2', 'Second fictitious news.', null),
            NewsItem::of('news-3', 'Third fictitious news.', null),
        ];
        $urssaf->newsItems(...['first' => $items[0], 'second' => $items[1], 'third' => $items[2]]);

        $all = $urssaf->newsFeed(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), PageRequest::firstPage());
        $second = $urssaf->newsFeed(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), PageRequest::of(2, 2));

        static::assertSame($items, $all->items());
        static::assertSame([1, 3, 1, 20], [$all->info()->totalPages(), $all->info()->totalCount(), $all->info()->currentPage(), $all->info()->perPage()]);
        static::assertSame([$items[2]], $second->items());
        static::assertSame([2, 3, 2, 2], [$second->info()->totalPages(), $second->info()->totalCount(), $second->info()->currentPage(), $second->info()->perPage()]);
        static::assertSame([Operation::GetNewsFeed, Operation::GetNewsFeed], $urssaf->calls());
    }

    public function testNewNewsItemsReplaceThePreviousOnes(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $urssaf->newsItems(NewsItem::of('news-1', 'Old.', null));
        $urssaf->newsItems(NewsItem::of('news-2', 'New.', null));

        $page = $urssaf->newsFeed(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), PageRequest::firstPage());

        static::assertSame(['news-2'], array_map(static fn(NewsItem $item): ?string => $item->idNews(), $page->items()));
    }

    public function testTheNewsFeedIsEmptyByDefaultOnTheProductionTargets(): void
    {
        foreach ([Environment::Production, Environment::Sandbox] as $environment) {
            $urssaf = SimulatorFixtures::gatewayFor($environment);

            $page = $urssaf->newsFeed(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), PageRequest::firstPage());

            static::assertTrue($page->isEmpty());
        }
    }

    public function testTheNewsFeedIsRefusedOnTheTestApi(): void
    {
        $urssaf = SimulatorFixtures::gatewayFor(Environment::TestApi);
        $urssaf->newsItems(NewsItem::of('news-1', 'Never served.', null));

        try {
            $urssaf->newsFeed(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), PageRequest::firstPage());
            static::fail('The news feed was served.');
        } catch (OperationNotSupported) {
            static::assertSame([Operation::GetNewsFeed], $urssaf->calls());
        }
    }

    public function testAQueuedFailureIsThrownByTheNewsFeedBeforeTheTargetIsChecked(): void
    {
        $urssaf = SimulatorFixtures::gatewayFor(Environment::TestApi);
        $urssaf->failNext(Operation::GetNewsFeed, new RuntimeException('down'));

        $this->expectExceptionMessage('down');

        $urssaf->newsFeed(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), PageRequest::firstPage());
    }

    private static function contentOf(DownloadedFile $file): string
    {
        $content = '';
        while (!$file->stream()->eof()) {
            $content .= $file->stream()->read(4);
        }

        return $content;
    }

    /**
     * @param-immediately-invoked-callable $call
     *
     * @param callable(): mixed $call
     */
    private static function notFound(callable $call): UrssafResourceNotFound
    {
        try {
            $call();
        } catch (UrssafResourceNotFound $notFound) {
            return $notFound;
        }

        static::fail('The resource was found.');
    }
}
