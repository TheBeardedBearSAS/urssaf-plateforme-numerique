<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class ShowSubmissionCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:submission:show';

    public function testPrintsTheSubmissionAsATable(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['id' => $id, '--tenant' => 'tenant-1']));

        static::assertSame([
            ['Field', 'Value'],
            ['submission_id', $id],
            ['tenant_id', 'tenant-1'],
            ['platform_id', 'platform-1'],
            ['external_reference', Scenario::EXTERNAL_REFERENCE],
            ['kind', 'identification'],
            ['declaration_type', '-'],
            ['file_name', Scenario::IDENTIFICATION_FILE],
            ['state', 'in_process'],
            ['attempts', '1'],
            ['urssaf_ref', HttpScript::IDENTIFICATION_REFERENCE],
            ['status', '-'],
            ['payment_status', '-'],
            ['last_polled_at', '-'],
            ['created_at', '2025-06-02T08:00:00Z'],
            ['updated_at', '2025-06-02T08:00:00Z'],
            ['error_codes', '-'],
        ], Output::rows($tester->getDisplay()));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_GET_IDENTIFICATION));
    }

    public function testPrintsTheSubmissionAsJson(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedDeclaration($harness, DeclarationType::Regularisation)->submissionId->value();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['id' => $id, '--tenant' => 'tenant-1', '--format' => 'json']));

        static::assertStringContainsString("{\n    \"submission_id\": \"" . $id . "\",\n", $tester->getDisplay());
        $data = self::decode($tester->getDisplay());
        static::assertSame($id, $data['submission_id']);
        static::assertSame('declaration', $data['kind']);
        static::assertSame('REGULARISATION', $data['declaration_type']);
        static::assertSame('in_process', $data['state']);
        static::assertSame(1, $data['attempts']);
        static::assertSame(HttpScript::DECLARATION_REFERENCE, $data['urssaf_ref']);
        static::assertNull($data['status']);
        static::assertStringNotContainsString('uploader@example.com', $tester->getDisplay());
        static::assertStringNotContainsString('user-0001', $tester->getDisplay());
    }

    public function testPrintsTheErrorCodesButNotTheirDescriptions(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_UPLOAD_IDENTIFICATION, 400, (string) file_get_contents(__DIR__ . '/../../../Fixtures/wire/error_response_handbuilt.json'));
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['id' => $id, '--tenant' => 'tenant-1']));

        static::assertContains(['error_codes', 'ERR_EXAMPLE_001, ERR_EXAMPLE_002'], Output::rows($tester->getDisplay()));
        static::assertStringNotContainsString('A description of the error', $tester->getDisplay());
        static::assertStringNotContainsString('A message describing the error', $tester->getDisplay());
    }

    public function testShowsWhatTheLastPollRead(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $harness->clock->sleep(700);
        $harness->command('urssaf:pn:submissions:sync')->execute([]);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['id' => $id, '--tenant' => 'tenant-1']));

        $rows = Output::rows($tester->getDisplay());
        static::assertContains(['state', 'accepted'], $rows);
        static::assertContains(['status', 'ACCEPTED'], $rows);
        static::assertContains(['last_polled_at', '2025-06-02T08:11:40Z'], $rows);
    }

    public function testNeedsATenant(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['id' => 'submission-1']));
        static::assertStringContainsString('The --tenant option is required.', $tester->getDisplay());
    }

    public function testRefusesAnUnknownFormat(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['id' => 'submission-1', '--tenant' => 'tenant-1', '--format' => 'xml']));
        static::assertStringContainsString('The --format option must be "table" or "json".', $tester->getDisplay());
    }

    public function testAnUnknownSubmissionFailsTheCommand(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute(['id' => 'unknown-submission', '--tenant' => 'tenant-1']));
        static::assertSame(1, $tester->execute(['id' => $id, '--tenant' => 'tenant-2']));
    }

    /**
     * @return array<string, string|int|null>
     */
    private static function decode(string $json): array
    {
        /** @var array<string, string|int|null> */
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
