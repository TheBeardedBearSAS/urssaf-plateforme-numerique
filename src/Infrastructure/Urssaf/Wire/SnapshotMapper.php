<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;

/**
 * Reduces a live read model to what is persisted after a poll: raw statuses, periods, report references, counters
 * keyed by their wire names and the last update date. The uploader (personal data, ADR 0009) is dropped.
 */
final class SnapshotMapper
{
    public static function fromIdentification(IdentificationSubmission $identification): SubmissionSnapshot
    {
        $details = $identification->details();
        $period = $identification->period();

        return new SubmissionSnapshot(
            $identification->processingStatus()->raw(),
            null,
            $period instanceof ReportedPeriod ? [$period] : [],
            $identification->reportFiles(),
            self::counters([
                'numberOfDeclaredUser' => $details?->numberOfDeclaredUser(),
                'numberOfIdentifiedUser' => $details?->numberOfIdentifiedUser(),
                'numberOfError' => $details?->numberOfError(),
            ]),
            $identification->dates()->lastUpdateDate(),
        );
    }

    public static function fromDeclaration(DeclarationSubmission $declaration): SubmissionSnapshot
    {
        $statistics = $declaration->statistics();

        return new SubmissionSnapshot(
            $declaration->processingStatus()->raw(),
            $declaration->paymentState()->raw(),
            $declaration->periods(),
            $declaration->reportFiles(),
            self::counters([
                'numberOfDeclaredUser' => $statistics?->numberOfDeclaredUser(),
                'totalRevenue' => $statistics?->totalRevenue(),
                'debitAmount' => $statistics?->debitAmount(),
                'paymentAmount' => $statistics?->paymentAmount(),
                'numberOfError' => $statistics?->numberOfError(),
            ]),
            $declaration->dates()->lastUpdateDate(),
        );
    }

    /**
     * @param array<string, ?DecimalNumber> $counters
     *
     * @return array<string, DecimalNumber>
     */
    private static function counters(array $counters): array
    {
        return array_filter($counters, static fn(?DecimalNumber $counter): bool => $counter instanceof DecimalNumber);
    }
}
