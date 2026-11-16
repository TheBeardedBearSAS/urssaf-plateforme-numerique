<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository;

use DateInterval;
use Doctrine\DBAL\ArrayParameterType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PollingWindow;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * The parameters of the "due for polling" DQL condition of {@see DoctrineSubmissionRepository}, by name, with their
 * value and type: the states and raw codes are derived from the domain enums, so that the SQL selection cannot drift
 * from {@see SubmissionState::isKnownToUrssaf()} and the settled payment (ACCEPTED and PROCESSED); the instants come
 * from the {@see PollingWindow} ("instant >= now - interval", see the repository about month-based intervals).
 *
 * Source: reference package (PollableParameters).
 */
final class PollingParameters
{
    /**
     * @return array<string, array{string|list<string>|Instant, ArrayParameterType|string|null}>
     */
    public static function of(Instant $now, PollingWindow $window): array
    {
        $processed = StateCodes::where(
            static fn(SubmissionState $state): bool => $state->isKnownToUrssaf() && $state !== SubmissionState::InProcess,
        );

        return [
            'inProcess' => [SubmissionState::InProcess->value, null],
            'processed' => [$processed, ArrayParameterType::STRING],
            'declaration' => [SubmissionKind::Declaration->value, null],
            'accepted' => [FileStatus::Accepted->value, null],
            'settledPayment' => [PaymentStatus::Processed->value, null],
            'createdSince' => [self::since($now, $window->maxPollingAge()), InstantType::NAME],
            'statusPolledUntil' => [self::since($now, $window->statusPollInterval()), InstantType::NAME],
            'paymentPolledUntil' => [self::since($now, $window->paymentPollInterval()), InstantType::NAME],
        ];
    }

    private static function since(Instant $now, DateInterval $interval): Instant
    {
        return $now->minus($interval);
    }
}
