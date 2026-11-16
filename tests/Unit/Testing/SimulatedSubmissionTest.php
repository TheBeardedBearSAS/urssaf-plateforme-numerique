<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedSequence;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedSubmission;

final class SimulatedSubmissionTest extends TestCase
{
    public function testItStartsAtTheFirstStepOfItsSequencesAtTheUploadInstant(): void
    {
        $submission = self::submission();

        static::assertSame(FileStatus::InProcess, $submission->status());
        static::assertSame(PaymentStatus::NotInitiated, $submission->paymentStatus());
        static::assertTrue($submission->updatedAt()->equals(Instant::fromIso8601('2026-03-10T08:00:00Z')));
    }

    public function testEachPollMovesBothSequencesAndTheUpdateInstant(): void
    {
        $submission = self::submission();
        $later = Instant::fromIso8601('2026-03-10T09:00:00Z');

        $submission->poll($later);
        static::assertSame(FileStatus::InProcess, $submission->status());
        static::assertSame(PaymentStatus::NotInitiated, $submission->paymentStatus());
        static::assertTrue($submission->updatedAt()->equals($later));

        $submission->poll($later);
        static::assertSame(FileStatus::Accepted, $submission->status());
        static::assertSame(PaymentStatus::Processed, $submission->paymentStatus());
    }

    public function testItBelongsToItsTenantPlatformAndKindOnly(): void
    {
        $submission = self::submission();
        $tenant = TenantId::fromString('tenant-1');
        $platform = PlatformId::fromString('platform-1');

        static::assertTrue($submission->isOf($tenant, $platform, SubmissionKind::Declaration));
        static::assertFalse($submission->isOf(TenantId::fromString('tenant-2'), $platform, SubmissionKind::Declaration));
        static::assertFalse($submission->isOf($tenant, PlatformId::fromString('platform-2'), SubmissionKind::Declaration));
        static::assertFalse($submission->isOf($tenant, $platform, SubmissionKind::Identification));
    }

    private static function submission(): SimulatedSubmission
    {
        return new SimulatedSubmission(
            TenantId::fromString('tenant-1'),
            PlatformId::fromString('platform-1'),
            SubmissionKind::Declaration,
            UrssafReference::fromString('simulated-1'),
            FileName::fromString('DPDE_202505_24001_000_20250428150823.xml.gz'),
            null,
            ['2025-05'],
            Instant::fromIso8601('2026-03-10T08:00:00Z'),
            new SimulatedSequence([FileStatus::InProcess, FileStatus::Accepted]),
            new SimulatedSequence([PaymentStatus::NotInitiated, PaymentStatus::Processed]),
        );
    }
}
