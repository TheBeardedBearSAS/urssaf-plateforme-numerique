<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class UploadIdentificationCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:identification:upload';

    public function testUploadsTheFile(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT), '--external-reference' => 'ref-1']);

        static::assertSame(0, $status);
        $rows = Output::rows($tester->getDisplay());
        static::assertSame(['Outcome', 'uploaded'], $rows[2]);
        static::assertSame(['State', 'in_process'], $rows[3]);
        static::assertSame(['URSSAF reference', HttpScript::IDENTIFICATION_REFERENCE], $rows[4]);
        static::assertSame(['Errors', '-'], $rows[5]);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_UPLOAD_IDENTIFICATION));
        static::assertStringNotContainsString('may have reached', $tester->getDisplay());
        static::assertStringNotContainsString('uploader@example.com', $tester->getDisplay());
        static::assertStringNotContainsString('user-0001', $tester->getDisplay());
    }

    public function testTheSameFileIsNotSentTwice(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);
        $input = ['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT), '--external-reference' => 'ref-1'];

        static::assertSame(0, $tester->execute($input));
        static::assertSame(0, $tester->execute($input));

        static::assertSame(['Outcome', 'replayed'], Output::rows($tester->getDisplay())[2]);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_UPLOAD_IDENTIFICATION));
    }

    public function testTheNameOptionIsTheNameSentToUrssaf(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);
        $name = 'DPID_202506_24001_000_20250428150823.xml.gz';

        $status = $tester->execute(['file' => ScratchFiles::file('local-copy.bin', Scenario::FILE_CONTENT), '--external-reference' => 'ref-1', '--name' => $name]);

        static::assertSame(0, $status);
        $body = (string) $harness->urssaf->requests(HttpScript::ROUTE_UPLOAD_IDENTIFICATION)[0]->getBody();
        static::assertStringContainsString($name, $body);
        static::assertStringNotContainsString('local-copy.bin', $body);
    }

    public function testTheBaseNameOfThePathIsTheDefaultName(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);

        $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT), '--external-reference' => 'ref-1']);

        static::assertStringContainsString(Scenario::IDENTIFICATION_FILE, (string) $harness->urssaf->requests(HttpScript::ROUTE_UPLOAD_IDENTIFICATION)[0]->getBody());
    }

    public function testARefusedFileFailsTheCommandAndPrintsOnlyTheErrorCodes(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_UPLOAD_IDENTIFICATION, 400, (string) file_get_contents(__DIR__ . '/../../../Fixtures/wire/error_response_handbuilt.json'));
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT), '--external-reference' => 'ref-1']);

        static::assertSame(1, $status);
        static::assertStringContainsString('upload_rejected', $tester->getDisplay());
        static::assertStringContainsString('ERR_EXAMPLE_001, ERR_EXAMPLE_002', $tester->getDisplay());
        static::assertStringNotContainsString('A description of the error', $tester->getDisplay());
        static::assertStringNotContainsString('A message describing the error', $tester->getDisplay());
    }

    public function testRefusedCredentialsFailTheCommand(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{}');
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT), '--external-reference' => 'ref-1']);

        static::assertSame(1, $status);
        static::assertSame(['Outcome', 'invalid_credentials'], Output::rows($tester->getDisplay())[2]);
    }

    public function testAnUnknownOutcomeFailsTheCommandAndTellsNotToUploadAgain(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->failTransport(HttpScript::ROUTE_UPLOAD_IDENTIFICATION);
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT), '--external-reference' => 'ref-1']);

        static::assertSame(1, $status);
        static::assertSame(['Outcome', 'outcome_unknown'], Output::rows($tester->getDisplay())[2]);
        static::assertStringContainsString('do not upload it again', $tester->getDisplay());
    }

    public function testATemporaryFailureFailsTheCommandWithThePackageMessage(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_UPLOAD_IDENTIFICATION, 429, '{}');
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT), '--external-reference' => 'ref-1']);

        static::assertSame(1, $status);
        static::assertStringContainsString('URSSAF is temporarily unavailable: the submission must be retried later.', $tester->getDisplay());
    }

    public function testAnotherFileUnderTheSameReferenceFailsTheCommand(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);
        $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT), '--external-reference' => 'ref-1']);

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, 'another fictitious content'), '--external-reference' => 'ref-1']);

        static::assertSame(1, $status);
        static::assertStringContainsString('The external reference "ref-1" is already recorded with another file.', $tester->getDisplay());
    }
}
