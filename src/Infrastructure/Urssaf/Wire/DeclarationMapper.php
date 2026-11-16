<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\PaymentState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Reads a `Declaration` object (api-reference section 2): no field is required, a missing one is null.
 */
final class DeclarationMapper
{
    /**
     * @throws UnexpectedUrssafResponse when a field has the wrong type or an unparseable value
     */
    public static function map(Payload $declaration, DateTimeZone $apiTimeZone): DeclarationSubmission
    {
        return new DeclarationSubmission(
            $declaration->optionalString('declarationFileRef'),
            $declaration->optionalString('declarationFileName'),
            $declaration->optionalString('declarationType'),
            array_map(ReportedPeriod::fromWire(...), $declaration->strings('period')),
            SubmissionFields::urssafReference($declaration),
            SubmissionFields::uploader($declaration),
            SubmissionFields::processingStatus($declaration),
            PaymentState::of($declaration->optionalString('paymentStatus'), $declaration->optionalString('paymentStatusLabel')),
            SubmissionFields::dates($declaration, $apiTimeZone),
            ReportFilesMapper::map($declaration),
            self::statistics($declaration->object('statistics')),
            $declaration->optionalString('concernedRegularisation'),
        );
    }

    /**
     * @param Payload $list the decoded array of `Declaration` objects
     *
     * @return list<DeclarationSubmission>
     *
     * @throws UnexpectedUrssafResponse
     */
    public static function mapList(Payload $list, DateTimeZone $apiTimeZone): array
    {
        return array_map(
            static fn(Payload $entry): DeclarationSubmission => self::map($entry, $apiTimeZone),
            $list->entries(),
        );
    }

    private static function statistics(?Payload $statistics): ?DeclarationStatistics
    {
        if (!$statistics instanceof Payload) {
            return null;
        }

        return DeclarationStatistics::of(
            NumberDecoding::field($statistics, 'numberOfDeclaredUser'),
            NumberDecoding::field($statistics, 'totalRevenue'),
            NumberDecoding::field($statistics, 'debitAmount'),
            NumberDecoding::field($statistics, 'paymentAmount'),
            NumberDecoding::field($statistics, 'numberOfError'),
        );
    }
}
