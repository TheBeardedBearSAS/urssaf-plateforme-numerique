<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class UploadDeclarationCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:declaration:upload';

    public function testUploadsADeclaration(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::DECLARATION_FILE, Scenario::FILE_CONTENT), '--type' => 'DECLARATION', '--external-reference' => 'ref-1']);

        static::assertSame(0, $status);
        $rows = Output::rows($tester->getDisplay());
        static::assertSame(['Outcome', 'uploaded'], $rows[2]);
        static::assertSame(['State', 'in_process'], $rows[3]);
        static::assertSame(['URSSAF reference', HttpScript::DECLARATION_REFERENCE], $rows[4]);
        $body = (string) $harness->urssaf->requests(HttpScript::ROUTE_UPLOAD_DECLARATION)[0]->getBody();
        static::assertStringContainsString(Scenario::DECLARATION_FILE, $body);
    }

    public function testUploadsARegularisationWithAnotherName(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute([
            'file' => ScratchFiles::file('copy.bin', Scenario::FILE_CONTENT),
            '--type' => 'REGULARISATION',
            '--external-reference' => 'ref-1',
            '--name' => Scenario::REGULARISATION_FILE,
        ]);

        static::assertSame(0, $status);
        $body = (string) $harness->urssaf->requests(HttpScript::ROUTE_UPLOAD_DECLARATION)[0]->getBody();
        static::assertStringContainsString(Scenario::REGULARISATION_FILE, $body);
    }

    public function testAnotherDeclarationOfTheSameTypeIsRefusedWhileOneIsInProcess(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);
        $tester->execute(['file' => ScratchFiles::file(Scenario::DECLARATION_FILE, Scenario::FILE_CONTENT), '--type' => 'DECLARATION', '--external-reference' => 'ref-1']);

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::DECLARATION_FILE, 'another fictitious content'), '--type' => 'DECLARATION', '--external-reference' => 'ref-2']);

        static::assertSame(1, $status);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_UPLOAD_DECLARATION));
        $other = $tester->execute(['file' => ScratchFiles::file(Scenario::REGULARISATION_FILE, Scenario::FILE_CONTENT), '--type' => 'REGULARISATION', '--external-reference' => 'ref-3']);
        static::assertSame(0, $other);
    }

    public function testARefusedFileFailsTheCommand(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_UPLOAD_DECLARATION, 400, (string) file_get_contents(__DIR__ . '/../../../Fixtures/wire/error_response_handbuilt.json'));
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::DECLARATION_FILE, Scenario::FILE_CONTENT), '--type' => 'DECLARATION', '--external-reference' => 'ref-1']);

        static::assertSame(1, $status);
        static::assertStringContainsString('ERR_EXAMPLE_001, ERR_EXAMPLE_002', $tester->getDisplay());
    }

    public function testRefusesAMissingOrUnknownType(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);
        $path = ScratchFiles::file(Scenario::DECLARATION_FILE, Scenario::FILE_CONTENT);

        static::assertSame(2, $tester->execute(['file' => $path, '--external-reference' => 'ref-1']));
        static::assertStringContainsString('The --type option is required.', $tester->getDisplay());
        static::assertSame(2, $tester->execute(['file' => $path, '--external-reference' => 'ref-1', '--type' => 'declaration']));
        static::assertStringContainsString('The --type option must be "DECLARATION" or "REGULARISATION".', $tester->getDisplay());
    }

    public function testRefusesAMissingExternalReference(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['file' => ScratchFiles::file(Scenario::DECLARATION_FILE, Scenario::FILE_CONTENT), '--type' => 'DECLARATION']));
        static::assertStringContainsString('The --external-reference option is required.', $tester->getDisplay());
    }

    public function testRefusesAnUnreadableFile(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['file' => ScratchFiles::directory() . '/missing.xml.gz', '--type' => 'DECLARATION', '--external-reference' => 'ref-1']));
        static::assertStringContainsString('file_source.unreadable', $tester->getDisplay());
    }

    public function testUsesTheTenantOption(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%', 'platform_id' => 'platform-2']]]);
        $tester = $harness->command(self::COMMAND);
        $path = ScratchFiles::file(Scenario::DECLARATION_FILE, Scenario::FILE_CONTENT);

        static::assertSame(2, $tester->execute(['file' => $path, '--type' => 'DECLARATION', '--external-reference' => 'ref-1']));
        static::assertSame(0, $tester->execute(['file' => $path, '--type' => 'DECLARATION', '--external-reference' => 'ref-1', '--tenant' => 'tenant-2']));
    }
}
