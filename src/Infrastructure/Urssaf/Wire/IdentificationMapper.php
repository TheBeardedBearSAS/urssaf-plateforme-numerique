<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationDetails;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Reads an `Identification` object (api-reference section 2): no field is required, a missing one is null.
 */
final class IdentificationMapper
{
    /**
     * @throws UnexpectedUrssafResponse when a field has the wrong type or an unparseable value
     */
    public static function map(Payload $identification, DateTimeZone $apiTimeZone): IdentificationSubmission
    {
        $period = $identification->optionalString('period');

        return new IdentificationSubmission(
            $identification->optionalString('identificationFileRef'),
            $identification->optionalString('identificationFileName'),
            $period === null ? null : ReportedPeriod::fromWire($period),
            SubmissionFields::urssafReference($identification),
            SubmissionFields::uploader($identification),
            SubmissionFields::processingStatus($identification),
            SubmissionFields::dates($identification, $apiTimeZone),
            ReportFilesMapper::map($identification),
            self::details($identification->object('details')),
        );
    }

    /**
     * @param Payload $list the decoded array of `Identification` objects
     *
     * @return list<IdentificationSubmission>
     *
     * @throws UnexpectedUrssafResponse
     */
    public static function mapList(Payload $list, DateTimeZone $apiTimeZone): array
    {
        return array_map(
            static fn(Payload $entry): IdentificationSubmission => self::map($entry, $apiTimeZone),
            $list->entries(),
        );
    }

    private static function details(?Payload $details): ?IdentificationDetails
    {
        if (!$details instanceof Payload) {
            return null;
        }

        return IdentificationDetails::of(
            NumberDecoding::field($details, 'numberOfDeclaredUser'),
            NumberDecoding::field($details, 'numberOfIdentifiedUser'),
            NumberDecoding::field($details, 'numberOfError'),
        );
    }
}
