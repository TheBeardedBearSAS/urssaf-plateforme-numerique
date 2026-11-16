<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class DownloadReportCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:report:download';

    public function testDownloadsAnIdentificationReport(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $path = ScratchFiles::directory() . '/' . bin2hex(random_bytes(4)) . '-identification-report.xml';
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['id' => $id, 'fileId' => HttpScript::GLOBAL_REPORT_FILE_ID, '--tenant' => 'tenant-1', '--output' => $path]);

        static::assertSame(0, $status);
        static::assertStringContainsString(\sprintf('%d byte(s) written to "%s".', \strlen(HttpScript::REPORT_CONTENT), $path), $tester->getDisplay());
        static::assertSame(HttpScript::REPORT_CONTENT, file_get_contents($path));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_DOWNLOAD_IDENTIFICATION_FILE));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_DOWNLOAD_DECLARATION_FILE));
        $url = (string) $harness->urssaf->requests(HttpScript::ROUTE_DOWNLOAD_IDENTIFICATION_FILE)[0]->getUri();
        static::assertStringContainsString('/identifications/' . HttpScript::IDENTIFICATION_REFERENCE . '/file/' . HttpScript::GLOBAL_REPORT_FILE_ID, $url);
    }

    public function testDownloadsADeclarationReport(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedDeclaration($harness)->submissionId->value();
        $path = ScratchFiles::directory() . '/' . bin2hex(random_bytes(4)) . '-declaration-report.xml';
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['id' => $id, 'fileId' => HttpScript::GLOBAL_REPORT_FILE_ID, '--tenant' => 'tenant-1', '--output' => $path]);

        static::assertSame(0, $status);
        static::assertSame(HttpScript::REPORT_CONTENT, file_get_contents($path));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_DOWNLOAD_DECLARATION_FILE));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_DOWNLOAD_IDENTIFICATION_FILE));
    }

    public function testNeverOverwritesAFile(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $path = ScratchFiles::file('existing.xml', 'precious content');
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['id' => $id, 'fileId' => HttpScript::GLOBAL_REPORT_FILE_ID, '--tenant' => 'tenant-1', '--output' => $path]);

        static::assertSame(1, $status);
        static::assertStringContainsString(\sprintf('The output file "%s" already exists: it is never overwritten.', $path), $tester->getDisplay());
        static::assertSame('precious content', file_get_contents($path));
    }

    public function testNeedsAnExistingDirectory(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['id' => $id, 'fileId' => HttpScript::GLOBAL_REPORT_FILE_ID, '--tenant' => 'tenant-1', '--output' => ScratchFiles::directory() . '/missing-directory/report.xml']);

        static::assertSame(1, $status);
        static::assertStringContainsString('does not exist.', $tester->getDisplay());
    }

    public function testASubmissionWithoutUrssafReferenceHasNoReport(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->failTransport(HttpScript::ROUTE_UPLOAD_IDENTIFICATION);
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $path = ScratchFiles::directory() . '/' . bin2hex(random_bytes(4)) . '-no-report.xml';
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['id' => $id, 'fileId' => HttpScript::GLOBAL_REPORT_FILE_ID, '--tenant' => 'tenant-1', '--output' => $path]);

        static::assertSame(1, $status);
        static::assertStringContainsString('The submission has no URSSAF reference yet: there is no report to download.', $tester->getDisplay());
        static::assertFileDoesNotExist($path);
    }

    public function testAFileUnknownToUrssafFailsTheCommandAndLeavesNoFileBehind(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $harness->urssaf->respond(HttpScript::ROUTE_DOWNLOAD_IDENTIFICATION_FILE, 404, '{}');
        $path = ScratchFiles::directory() . '/' . bin2hex(random_bytes(4)) . '-unknown-report.xml';
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['id' => $id, 'fileId' => HttpScript::GLOBAL_REPORT_FILE_ID, '--tenant' => 'tenant-1', '--output' => $path]);

        static::assertSame(1, $status);
        static::assertFileDoesNotExist($path);
    }

    public function testNeedsATenantAndAnOutput(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['id' => 'submission-1', 'fileId' => 'file-1', '--output' => ScratchFiles::directory() . '/x.xml']));
        static::assertStringContainsString('The --tenant option is required.', $tester->getDisplay());
        static::assertSame(2, $tester->execute(['id' => 'submission-1', 'fileId' => 'file-1', '--tenant' => 'tenant-1']));
        static::assertStringContainsString('The --output option is required.', $tester->getDisplay());
    }

    public function testRefusesAnInvalidFileId(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['id' => 'submission-1', 'fileId' => 'a/b', '--tenant' => 'tenant-1', '--output' => ScratchFiles::directory() . '/x.xml']));
        static::assertStringContainsString('file_reference', $tester->getDisplay());
    }

    public function testAnUnknownSubmissionFailsTheCommand(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(1, $tester->execute(['id' => 'unknown-submission', 'fileId' => 'file-1', '--tenant' => 'tenant-1', '--output' => ScratchFiles::directory() . '/x.xml']));
    }
}
