<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FixedClock;

final class SimulatedPlatformGatewayReadTest extends TestCase
{
    public function testAnIdentificationIsReadWithTheNextStatusOfTheDefaultSequence(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $reference = SimulatorFixtures::uploadIdentification($urssaf, 'DPID_202506_24001_000_20250428150823.xml.gz');

        $first = $urssaf->getIdentification(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);
        $second = $urssaf->getIdentification(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);
        $third = $urssaf->getIdentification(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);

        static::assertSame([FileStatus::InProcess, FileStatus::Accepted, FileStatus::Accepted], [
            $first->processingStatus()->status(),
            $second->processingStatus()->status(),
            $third->processingStatus()->status(),
        ]);
        static::assertSame('IN_PROCESS', $first->processingStatus()->raw());
        static::assertSame('Simulated status.', $first->processingStatus()->label());
        static::assertSame('simulated-1', $first->identificationFileRef());
        static::assertSame('2025-06', $first->period()?->raw());
        static::assertSame($reference, $first->urssafRef());
        static::assertSame('simulated-user', $first->uploader()->userId());
        static::assertSame('simulated.user@example.test', $first->uploader()->userEmail());
        static::assertNull($first->details());
        static::assertSame([Operation::UploadIdentification, ...array_fill(0, 3, Operation::GetIdentification)], $urssaf->calls());
    }

    public function testADeclarationIsReadWithItsTypePeriodAndPaymentStatus(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $reference = SimulatorFixtures::uploadDeclaration($urssaf);

        $declaration = $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);

        static::assertSame('simulated-1', $declaration->declarationFileRef());
        static::assertSame('DECLARATION', $declaration->rawDeclarationType());
        static::assertSame($reference, $declaration->urssafRef());
        static::assertSame(['2025-05'], SimulatorFixtures::periods($declaration));
        static::assertSame(FileStatus::InProcess, $declaration->processingStatus()->status());
        static::assertSame('NOT_INITIATED', $declaration->paymentState()->raw());
        static::assertSame('Simulated payment status.', $declaration->paymentState()->label());
        static::assertSame('simulated-user', $declaration->uploader()->userId());
        static::assertSame('simulated.user@example.test', $declaration->uploader()->userEmail());
        static::assertSame([Operation::UploadDeclaration, Operation::GetDeclaration], $urssaf->calls());
        static::assertNull($declaration->statistics());
        static::assertNull($declaration->concernedRegularisation());
    }

    public function testTheStatusSequencesAreScriptedForTheUploadsThatFollow(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $before = SimulatorFixtures::uploadDeclaration($urssaf);
        // String-keyed variadics exercise the array_values() normalisation: the steps stay in the given order.
        $urssaf->progressThrough(FileStatus::InProcess, ...['then' => FileStatus::Rejected]);
        $urssaf->progressPaymentThrough(PaymentStatus::Processing, ...['then' => PaymentStatus::Declared, 'finally' => PaymentStatus::Processed]);
        $after = SimulatorFixtures::uploadDeclaration($urssaf);

        $statuses = [];
        for ($read = 0; $read < 4; ++$read) {
            $declaration = $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $after);
            $statuses[] = \sprintf('%s/%s', $declaration->processingStatus()->raw(), $declaration->paymentState()->raw());
        }
        $unchanged = $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $before);

        static::assertSame(['IN_PROCESS/PROCESSING', 'REJECTED/DECLARED', 'REJECTED/PROCESSED', 'REJECTED/PROCESSED'], $statuses);
        static::assertSame('IN_PROCESS', $unchanged->processingStatus()->raw());
        static::assertSame('NOT_INITIATED', $unchanged->paymentState()->raw());
    }

    public function testTheDatesFollowTheClockAndTheTreatmentDateAppearsOnceTheFileIsFinal(): void
    {
        $clock = new FixedClock('2026-03-10T08:00:00Z');
        $urssaf = SimulatorFixtures::gateway($clock);
        $reference = SimulatorFixtures::uploadDeclaration($urssaf);

        $processing = $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);
        $clock->moveTo('2026-03-10T09:15:00Z');
        $accepted = $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);

        static::assertNull($processing->dates()->treatmentDate());
        static::assertSame('2026-03-10T08:00:00Z', $processing->dates()->lastUpdateDate()?->format());
        static::assertSame('2026-03-10T08:00:00Z', $accepted->dates()->uploadDate()?->raw());
        static::assertSame('2026-03-10T09:15:00Z', $accepted->dates()->lastUpdateDate()?->format());
        $treatment = $accepted->dates()->treatmentDate();
        static::assertNotNull($treatment);
        static::assertSame('2026-03-10T09:15:00Z', $treatment->raw());
        static::assertSame('2026-03-10T09:15:00Z', $treatment->instant()?->format());
    }

    public function testTheTreatmentDateAppearsForARejectedFileToo(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $urssaf->progressThrough(FileStatus::Rejected);
        $reference = SimulatorFixtures::uploadIdentification($urssaf);

        $identification = $urssaf->getIdentification(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);

        static::assertNotNull($identification->dates()->treatmentDate());
    }

    public function testTheDetailedReportIsListedOnceTheFileIsAccepted(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $urssaf->progressThrough(FileStatus::InProcess, FileStatus::Rejected, FileStatus::Accepted);
        $reference = SimulatorFixtures::uploadIdentification($urssaf);

        $reports = [];
        for ($read = 0; $read < 3; ++$read) {
            $reports[] = $urssaf->getIdentification(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference)->reportFiles();
        }

        $global = $reports[0]->globalReport();
        static::assertNotNull($global);
        static::assertSame('simulated-1-global-report', $global->fileId());
        static::assertSame('global-report', $global->name());
        static::assertNull($reports[0]->detailedReport());
        static::assertNull($reports[1]->detailedReport());
        $detailed = $reports[2]->detailedReport();
        static::assertNotNull($detailed);
        static::assertSame('simulated-1-detailed-report', $detailed->fileId());
        static::assertSame('detailed-report', $detailed->name());
        static::assertNull($reports[2]->usersAnomaliesReport());
        static::assertNull($reports[2]->affiliatedUsersFile());
    }

    public function testAReferenceUnknownToTheSimulatorIsNotFound(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $unknown = UrssafReference::fromString('simulated-9');

        $identification = self::notFound(static fn(): IdentificationSubmission => $urssaf->getIdentification(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $unknown));
        $declaration = self::notFound(static fn(): DeclarationSubmission => $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $unknown));

        static::assertSame(Operation::GetIdentification, $identification->operation());
        static::assertSame(Operation::GetDeclaration, $declaration->operation());
        static::assertTrue($declaration->errors()->isEmpty());
        static::assertSame(404, $declaration->getCode());
    }

    public function testASubmissionIsNotVisibleToAnotherTenantPlatformOrKind(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $identification = SimulatorFixtures::uploadIdentification($urssaf);
        $declaration = SimulatorFixtures::uploadDeclaration($urssaf);
        $tenant = SimulatorFixtures::tenant();
        $platform = SimulatorFixtures::platform();

        static::assertSame(Operation::GetIdentification, self::notFound(static fn(): IdentificationSubmission => $urssaf->getIdentification(SimulatorFixtures::otherTenant(), $platform, $identification))->operation());
        static::assertSame(Operation::GetIdentification, self::notFound(static fn(): IdentificationSubmission => $urssaf->getIdentification($tenant, SimulatorFixtures::otherPlatform(), $identification))->operation());
        static::assertSame(Operation::GetIdentification, self::notFound(static fn(): IdentificationSubmission => $urssaf->getIdentification($tenant, $platform, $declaration))->operation());
        static::assertSame(Operation::GetDeclaration, self::notFound(static fn(): DeclarationSubmission => $urssaf->getDeclaration($tenant, $platform, $identification))->operation());
        static::assertSame('simulated-1', $urssaf->getIdentification($tenant, $platform, $identification)->identificationFileRef());
    }

    public function testAQueuedFailureIsThrownBeforeTheRead(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $reference = SimulatorFixtures::uploadIdentification($urssaf);
        $urssaf->failNext(Operation::GetIdentification, new RuntimeException('down'));

        try {
            $urssaf->getIdentification(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);
            static::fail('The failure was not thrown.');
        } catch (RuntimeException $failure) {
            static::assertSame('down', $failure->getMessage());
        }

        // The failed read did not move the sequence.
        static::assertSame('IN_PROCESS', $urssaf->getIdentification(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference)->processingStatus()->raw());
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

        static::fail('The reference was found.');
    }
}
