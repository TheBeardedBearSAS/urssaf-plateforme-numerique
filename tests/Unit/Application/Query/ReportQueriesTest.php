<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\BinaryStream;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFileHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeedHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\PageInfo;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

/**
 * The news feed and report file queries resolve the platform of the tenant, call the gateway once and return its answer untouched.
 */
final class ReportQueriesTest extends TestCase
{
    private const TARGET = 'tenant-1|platform-1';

    public function testReadsTheNewsFeed(): void
    {
        $harness = new Harness();
        $page = Page::of([NewsItem::of('news-1', 'A fictitious news.', null)], PageInfo::unknown());
        $harness->gateway->willReadNewsFeed($page);
        $request = PageRequest::of(2, 10);

        $result = (new GetNewsFeedHandler($harness->gateway, $harness->platforms))(new GetNewsFeed(Harness::tenant(), $request));

        static::assertSame($page, $result);
        static::assertSame([$request], $harness->gateway->recorded()->newsFeedRequests());
        static::assertSame([self::TARGET], $harness->gateway->recorded()->targets());
    }

    public function testDownloadsAnIdentificationReportFile(): void
    {
        $harness = new Harness();
        $download = new DownloadedFile(self::stream(), 'application/pdf', null);
        $harness->gateway->willDownloadIdentificationFile($download);

        $result = (new DownloadReportFileHandler($harness->gateway, $harness->platforms))(
            DownloadReportFile::identification(Harness::tenant(), UrssafReference::fromString('urssaf-1'), FileReference::of('file-9', null)),
        );

        static::assertSame($download, $result);
        static::assertSame(['download_identification:urssaf-1:file-9'], $harness->gateway->recorded()->calls());
        static::assertSame([self::TARGET], $harness->gateway->recorded()->targets());
    }

    public function testDownloadsADeclarationReportFile(): void
    {
        $harness = new Harness();
        $download = new DownloadedFile(self::stream(), null, 'attachment');
        $harness->gateway->willDownloadDeclarationFile($download);

        $result = (new DownloadReportFileHandler($harness->gateway, $harness->platforms))(
            DownloadReportFile::declaration(Harness::tenant(), UrssafReference::fromString('urssaf-2'), FileReference::of('file-8', 'report.pdf')),
        );

        static::assertSame($download, $result);
        static::assertSame(['download_declaration:urssaf-2:file-8'], $harness->gateway->recorded()->calls());
    }

    public function testQueriesKeepWhatTheyWereGiven(): void
    {
        $tenant = Harness::tenant();
        $reference = UrssafReference::fromString('urssaf-1');
        $file = FileReference::of('file-1', null);

        $identification = DownloadReportFile::identification($tenant, $reference, $file);
        $declaration = DownloadReportFile::declaration($tenant, $reference, $file);

        static::assertSame('identification', $identification->kind->value);
        static::assertSame('declaration', $declaration->kind->value);
        static::assertSame($file, $declaration->file);
        static::assertSame($reference, $declaration->reference);
        static::assertSame($tenant, $declaration->tenantId);
    }

    private static function stream(): BinaryStream
    {
        return new class implements BinaryStream {
            public function read(int $length): string
            {
                return '';
            }

            public function eof(): bool
            {
                return true;
            }

            public function copyTo(mixed $resource): int
            {
                return 0;
            }

            public function close(): void {}
        };
    }
}
