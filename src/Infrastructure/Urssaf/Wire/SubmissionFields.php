<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ApiTimestamp;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingDates;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Uploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Fields shared by the `Identification` and `Declaration` objects (api-reference section 2).
 */
final class SubmissionFields
{
    /**
     * @throws UnexpectedUrssafResponse when `urssafRef` has the wrong type or is not a valid reference
     */
    public static function urssafReference(Payload $submission): ?UrssafReference
    {
        $value = $submission->optionalString('urssafRef');
        if ($value === null) {
            return null;
        }

        try {
            return UrssafReference::fromString($value);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('field "urssafRef" is not a valid reference', $exception);
        }
    }

    /**
     * @throws UnexpectedUrssafResponse
     */
    public static function uploader(Payload $submission): Uploader
    {
        return Uploader::of($submission->optionalString('userId'), $submission->optionalString('userEmail'));
    }

    /**
     * @throws UnexpectedUrssafResponse
     */
    public static function processingStatus(Payload $submission): ProcessingStatus
    {
        return ProcessingStatus::of($submission->optionalString('status'), $submission->optionalString('statusLabel'));
    }

    /**
     * @throws UnexpectedUrssafResponse when `lastUpdateDate` is not a date-time
     */
    public static function dates(Payload $submission, DateTimeZone $apiTimeZone): ProcessingDates
    {
        $lastUpdate = $submission->optionalString('lastUpdateDate');
        $upload = $submission->optionalString('uploadDate');
        $treatment = $submission->optionalString('treatmentDate');

        try {
            $lastUpdateDate = $lastUpdate === null ? null : Instant::fromLocalDateTime($lastUpdate, $apiTimeZone);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('field "lastUpdateDate" is not a date-time', $exception);
        }

        return ProcessingDates::of(
            $lastUpdateDate,
            $upload === null ? null : ApiTimestamp::of($upload, $apiTimeZone),
            $treatment === null ? null : ApiTimestamp::of($treatment, $apiTimeZone),
        );
    }
}
