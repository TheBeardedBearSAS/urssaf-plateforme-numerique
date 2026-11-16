<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FixedClock;

/**
 * One method per behaviour of the simulator (uploads).
 *
 * @mago-expect lint:too-many-methods
 */
final class SimulatedPlatformGatewayUploadTest extends TestCase
{
    public function testUploadsGetSuccessiveSimulatedReferences(): void
    {
        $urssaf = SimulatorFixtures::gateway();

        $first = SimulatorFixtures::uploadIdentification($urssaf);
        $second = SimulatorFixtures::uploadDeclaration($urssaf);
        $third = SimulatorFixtures::uploadDeclaration($urssaf, 'DPRE_202505_24001_000_20250428150823.xml.gz', DeclarationType::Regularisation);

        static::assertSame(['simulated-1', 'simulated-2', 'simulated-3'], [$first->value(), $second->value(), $third->value()]);
        static::assertSame(
            [Operation::UploadIdentification, Operation::UploadDeclaration, Operation::UploadDeclaration],
            $urssaf->calls(),
        );
    }

    public function testTheUploadedFileIsRememberedWithItsKindTypeAndName(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $identification = SimulatorFixtures::uploadIdentification($urssaf, 'DPID_202506_24001_000_20250428150823.xml.gz');
        $regularisation = SimulatorFixtures::uploadDeclaration($urssaf, 'DPRE_202505_24001_000_20250428150823.xml.gz', DeclarationType::Regularisation);

        $readIdentification = $urssaf->getIdentification(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $identification);
        $readDeclaration = $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $regularisation);

        static::assertSame('DPID_202506_24001_000_20250428150823.xml.gz', $readIdentification->identificationFileName());
        static::assertSame('DPRE_202505_24001_000_20250428150823.xml.gz', $readDeclaration->declarationFileName());
        static::assertSame('REGULARISATION', $readDeclaration->rawDeclarationType());
    }

    public function testTheUploadIsNotStoredUnderAnotherTenantOrPlatform(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $reference = $urssaf->uploadIdentification(SimulatorFixtures::otherTenant(), SimulatorFixtures::otherPlatform(), SimulatorFixtures::file('DPID_202505_x.xml.gz'));

        $found = $urssaf->searchIdentifications(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), SubmissionSearch::all());
        $own = $urssaf->searchIdentifications(SimulatorFixtures::otherTenant(), SimulatorFixtures::otherPlatform(), SubmissionSearch::all());

        static::assertSame('simulated-1', $reference->value());
        static::assertTrue($found->isEmpty());
        static::assertCount(1, $own);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function periodsInFileNames(): iterable
    {
        yield 'compact month' => ['DPDE_202505_24001_000_20250428150823.xml.gz', '2025-05'];
        yield 'dashed month' => ['DPID_4300001_2026-05_001.xml.gz', '2026-05'];
        yield 'december' => ['DPDE_202512_24001.xml.gz', '2025-12'];
        yield 'january' => ['DPDE_202501_24001.xml.gz', '2025-01'];
        yield 'month zero is no month' => ['DPDE_202500_24001.xml.gz', '2026-03'];
        yield 'month thirteen is no month' => ['DPDE_202513_24001.xml.gz', '2026-03'];
        yield 'longer digit run is no month' => ['DPDE_2025051_24001.xml.gz', '2026-03'];
        yield 'digit run before is no month' => ['DPDE_12025051_x.xml.gz', '2026-03'];
        yield 'no period falls back to the month of the clock' => ['report.xml.gz', '2026-03'];
    }

    #[DataProvider('periodsInFileNames')]
    public function testThePeriodIsReadFromTheFileName(string $name, string $expected): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $reference = SimulatorFixtures::uploadDeclaration($urssaf, $name);

        $declaration = $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);

        static::assertSame([$expected], SimulatorFixtures::periods($declaration));
    }

    public function testTheNextUploadIsRefusedWithTheGivenCodesAndConsumesNoReference(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $urssaf->rejectNextUpload('ERR_FIRST', 'ERR_SECOND');

        $refusal = self::refusal(static fn(): UrssafReference => SimulatorFixtures::uploadDeclaration($urssaf));
        $accepted = SimulatorFixtures::uploadDeclaration($urssaf);

        static::assertSame(Operation::UploadDeclaration, $refusal->operation());
        static::assertSame(400, $refusal->httpStatus());
        static::assertSame(['ERR_FIRST', 'ERR_SECOND'], $refusal->errors()->codes());
        static::assertSame('Simulated error.', $refusal->errors()->all()[0]->message());
        static::assertSame('Simulated by the test gateway.', $refusal->errors()->all()[0]->description());
        static::assertSame('simulated-1', $accepted->value());
        static::assertSame([Operation::UploadDeclaration, Operation::UploadDeclaration], $urssaf->calls());
    }

    public function testRefusalsAreConsumedInOrderByAnyUpload(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $urssaf->rejectNextUpload('ERR_FIRST');
        $urssaf->rejectNextUpload('ERR_SECOND');

        $first = self::refusal(static fn(): UrssafReference => SimulatorFixtures::uploadIdentification($urssaf));
        $second = self::refusal(static fn(): UrssafReference => SimulatorFixtures::uploadDeclaration($urssaf));

        static::assertSame(Operation::UploadIdentification, $first->operation());
        static::assertSame(['ERR_FIRST'], $first->errors()->codes());
        static::assertSame(['ERR_SECOND'], $second->errors()->codes());
        static::assertSame('simulated-1', SimulatorFixtures::uploadIdentification($urssaf)->value());
    }

    public function testQueuedFailuresAreThrownInOrderByTheirOperationOnly(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $urssaf->failNext(Operation::UploadIdentification, new RuntimeException('first'));
        $urssaf->failNext(Operation::UploadIdentification, new RuntimeException('second'));
        $thrown = [];

        $declaration = SimulatorFixtures::uploadDeclaration($urssaf);
        foreach ([1, 2, 3] as $attempt) {
            try {
                SimulatorFixtures::uploadIdentification($urssaf);
                $thrown[] = 'none' . $attempt;
            } catch (RuntimeException $failure) {
                $thrown[] = $failure->getMessage();
            }
        }

        static::assertSame('simulated-1', $declaration->value());
        static::assertSame(['first', 'second', 'none3'], $thrown);
        static::assertCount(4, $urssaf->calls());
    }

    public function testAFailedUploadStoresNothing(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $urssaf->failNext(Operation::UploadDeclaration, new RuntimeException('down'));

        try {
            SimulatorFixtures::uploadDeclaration($urssaf);
            static::fail('The failure was not thrown.');
        } catch (RuntimeException) {
            static::assertSame('simulated-1', SimulatorFixtures::uploadDeclaration($urssaf)->value());
        }
    }

    public function testTheUploadInstantIsTheOneOfTheClock(): void
    {
        $clock = new FixedClock('2026-03-10T08:00:00Z');
        $urssaf = SimulatorFixtures::gateway($clock);
        $reference = SimulatorFixtures::uploadDeclaration($urssaf);
        $clock->moveTo('2026-03-11T10:30:00Z');

        $declaration = $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $reference);

        static::assertSame('2026-03-10T08:00:00Z', $declaration->dates()->uploadDate()?->raw());
        static::assertSame('2026-03-11T10:30:00Z', $declaration->dates()->lastUpdateDate()?->format());
    }

    /**
     * @param-immediately-invoked-callable $call
     *
     * @param callable(): mixed $call
     */
    private static function refusal(callable $call): UrssafRequestRejected
    {
        try {
            $call();
        } catch (UrssafRequestRejected $refusal) {
            return $refusal;
        }

        static::fail('The upload was not refused.');
    }
}
