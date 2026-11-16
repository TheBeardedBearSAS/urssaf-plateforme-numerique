<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class ResolveSubmissionCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:submission:resolve';
    private const REFERENCE = 'urssaf-ref-from-the-operator';

    public function testRecordsTheReferenceUrssafHoldsTheFileUnder(): void
    {
        $harness = Harness::boot();
        $id = self::unknownOutcome($harness);
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['id' => $id, '--tenant' => 'tenant-1', '--urssaf-ref' => self::REFERENCE]);

        static::assertSame(0, $status);
        $rows = Output::rows($tester->getDisplay());
        static::assertContains(['state', 'in_process'], $rows);
        static::assertContains(['urssaf_ref', self::REFERENCE], $rows);
    }

    public function testRecordsThatTheFileNeverReachedUrssaf(): void
    {
        $harness = Harness::boot();
        $id = self::unknownOutcome($harness);
        $tester = $harness->command(self::COMMAND);

        $status = $tester->execute(['id' => $id, '--tenant' => 'tenant-1', '--not-received' => true]);

        static::assertSame(0, $status);
        static::assertContains(['state', 'not_received'], Output::rows($tester->getDisplay()));
    }

    public function testNeedsExactlyOneDecision(): void
    {
        $harness = Harness::boot();
        $id = self::unknownOutcome($harness);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['id' => $id, '--tenant' => 'tenant-1']));
        static::assertStringContainsString('Give one of --urssaf-ref or --not-received.', $tester->getDisplay());
        static::assertSame(2, $tester->execute(['id' => $id, '--tenant' => 'tenant-1', '--urssaf-ref' => self::REFERENCE, '--not-received' => true]));
        static::assertStringContainsString('Give one of --urssaf-ref or --not-received.', $tester->getDisplay());
    }

    public function testNeedsATenant(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['id' => 'submission-1', '--not-received' => true]));
        static::assertStringContainsString('The --tenant option is required.', $tester->getDisplay());
    }

    public function testRefusesAnInvalidReference(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['id' => 'submission-1', '--tenant' => 'tenant-1', '--urssaf-ref' => '']));
        static::assertStringContainsString('urssaf_reference', $tester->getDisplay());
    }

    public function testAnUnknownSubmissionFailsTheCommand(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(1, $tester->execute(['id' => 'unknown-submission', '--tenant' => 'tenant-1', '--not-received' => true]));
    }

    public function testASubmissionOfAnotherTenantIsNotFound(): void
    {
        $harness = Harness::boot();
        $id = self::unknownOutcome($harness);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute(['id' => $id, '--tenant' => 'tenant-2', '--not-received' => true]));
    }

    public function testASubmissionWhoseOutcomeIsKnownCannotBeResolved(): void
    {
        $harness = Harness::boot();
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute(['id' => $id, '--tenant' => 'tenant-1', '--not-received' => true]));
    }

    public function testAReferenceAlreadyHeldByAnotherSubmissionIsRefused(): void
    {
        $harness = Harness::boot();
        Scenario::uploadedIdentification($harness, 'tenant-1', 'ref-1');
        $id = self::unknownOutcome($harness, 'ref-2');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute(['id' => $id, '--tenant' => 'tenant-1', '--urssaf-ref' => HttpScript::IDENTIFICATION_REFERENCE]));
        static::assertStringContainsString('Another submission of the tenant already holds this URSSAF reference.', $tester->getDisplay());
    }

    private static function unknownOutcome(Harness $harness, string $reference = Scenario::EXTERNAL_REFERENCE): string
    {
        $harness->urssaf->failTransport(HttpScript::ROUTE_UPLOAD_IDENTIFICATION);
        $id = Scenario::uploadedIdentification($harness, Scenario::TENANT, $reference)->submissionId->value();
        $harness->urssaf->respond(HttpScript::ROUTE_UPLOAD_IDENTIFICATION, 200, HttpScript::uploadBody(HttpScript::IDENTIFICATION_REFERENCE));

        return $id;
    }
}
