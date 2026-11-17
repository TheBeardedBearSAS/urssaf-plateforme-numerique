<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\FileFingerprinter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\SubmissionUploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Platform\InMemoryPlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

final class UploadIdentificationHandlerTest extends TestCase
{
    public function testANewFileIsRecordedThenSentOutsideAnyTransactionThenMarkedUploaded(): void
    {
        $harness = new Harness();
        $harness->gateway->willUploadIdentification(UrssafReference::fromString('urssaf-new'));
        $file = UploadScenario::file('content');

        $report = UploadScenario::identification($harness, $file);

        static::assertSame(UploadOutcome::Uploaded, $report->outcome);
        static::assertSame(UploadScenario::FIRST_ID, $report->submissionId->value());
        static::assertSame(SubmissionState::InProcess, $report->state);
        static::assertSame('urssaf-new', $report->urssafReference?->value());
        static::assertSame([], $report->errors);
        static::assertSame(
            ['begin', 'commit', 'upload_identification:' . UploadScenario::FILE, 'begin', 'commit', 'publish:SubmissionUploaded'],
            $harness->journal->entries(),
        );
        static::assertSame([Harness::TENANT . '|' . Harness::PLATFORM], $harness->gateway->recorded()->targets());
        static::assertSame(['urssaf.pn.submission.uploaded'], self::outboxTypes($harness));
        static::assertSame(1, $file->openedCount());
        static::assertTrue($file->allClosed());

        $stored = UploadScenario::stored($harness, UploadScenario::FIRST_ID);
        $identity = $stored->identity();
        static::assertSame(SubmissionState::InProcess, $stored->state());
        static::assertSame('urssaf-new', $stored->urssafReference()?->value());
        static::assertSame(1, $stored->attempts());
        static::assertSame(Harness::NOW, $stored->createdAt()->format());
        static::assertSame(Harness::PLATFORM, $identity->platformId()->value());
        static::assertSame(UploadScenario::REFERENCE, $identity->externalReference()->value());
        static::assertSame(SubmissionKind::Identification, $identity->kind());
        static::assertNull($identity->declarationType());
        static::assertSame(UploadScenario::FILE, $identity->fileName()->value());
        static::assertTrue($identity->fingerprint()->equals(
            FileFingerprinter::fingerprint(UploadScenario::file('content'), SubmissionKind::Identification, null),
        ));
    }

    public function testARefusedFileKeepsTheCodesAndMessagesOfTheErrorsButNotTheirDescriptions(): void
    {
        $harness = new Harness();
        $harness->gateway->willUploadIdentification(UrssafRequestRejected::withErrors(Operation::UploadIdentification, 400, ApiErrors::of(
            ApiError::of('ERR_FORMAT', 'Format invalide', 'Ligne 3 : valeur inattendue'),
            ApiError::of('ERR_PERIODE', 'Période invalide', 'Mois hors plage'),
        )));

        $report = UploadScenario::identification($harness, UploadScenario::file('content'));

        $expected = [SubmissionError::of('ERR_FORMAT', 'Format invalide'), SubmissionError::of('ERR_PERIODE', 'Période invalide')];
        static::assertSame(UploadOutcome::Rejected, $report->outcome);
        static::assertSame(SubmissionState::UploadRejected, $report->state);
        static::assertNull($report->urssafReference);
        static::assertEquals($expected, $report->errors);
        static::assertEquals($expected, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->errors());
        static::assertSame(['SubmissionUploadRejected'], $harness->events->names());
        static::assertSame(['urssaf.pn.submission.upload_rejected'], self::outboxTypes($harness));
    }

    public function testRefusedCredentialsAreReportedWithoutAnIntegrationEvent(): void
    {
        $harness = new Harness();
        $harness->gateway->willUploadIdentification(UrssafAuthenticationFailed::withStatus(401));

        $report = UploadScenario::identification($harness, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::InvalidCredentials, $report->outcome);
        static::assertSame(SubmissionState::InvalidCredentials, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->state());
        static::assertSame(['SubmissionUploadFailed'], $harness->events->names());
        static::assertSame([], self::outboxTypes($harness));
    }

    public function testAnUploadThatMayHaveReachedUrssafIsReportedAsUnknownNotThrown(): void
    {
        $harness = new Harness();
        $harness->gateway->willUploadIdentification(OutcomeUnknown::afterStatus(Operation::UploadIdentification, 502));

        $report = UploadScenario::identification($harness, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::OutcomeUnknown, $report->outcome);
        static::assertSame(SubmissionState::OutcomeUnknown, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->state());
        static::assertSame(['SubmissionOutcomeUnknown'], $harness->events->names());
        static::assertSame(['urssaf.pn.submission.outcome_unknown'], self::outboxTypes($harness));
    }

    public function testATemporaryFailureMovesTheRecordToErrorThenIsThrownAsRetryable(): void
    {
        $harness = new Harness();
        $limited = RateLimitExceeded::forOperation(Operation::UploadIdentification, 30);
        $harness->gateway->willUploadIdentification($limited);

        try {
            UploadScenario::identification($harness, UploadScenario::file('content'));
            static::fail('The temporary failure must be thrown.');
        } catch (RetryableUrssafFailure $failure) {
            static::assertSame($limited, $failure->getPrevious());
        }

        static::assertSame(SubmissionState::Error, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->state());
        static::assertSame(['begin', 'commit', 'publish:SubmissionUploadFailed'], \array_slice($harness->journal->entries(), -3));
    }

    public function testATenantWithoutPlatformIsRefusedBeforeAnything(): void
    {
        $harness = new Harness();
        $uploader = new SubmissionUploader(
            $harness->submissions,
            $harness->gateway,
            new InMemoryPlatformResolver([]),
            $harness->ids,
            $harness->clock,
            $harness->unitOfWork,
            $harness->policy,
        );

        try {
            UploadScenario::identificationThrough($uploader, UploadScenario::file('content'));
            static::fail('A tenant without platform must be refused.');
        } catch (RecordNotFound $notFound) {
            static::assertSame('platform.not_found', $notFound->violationCode());
        }

        static::assertSame([], $harness->journal->entries());
        static::assertNull($harness->submissions->findByExternalReference(Harness::tenant(), ExternalReference::fromString(UploadScenario::REFERENCE)));
    }

    /**
     * @return list<string>
     */
    private static function outboxTypes(Harness $harness): array
    {
        return array_map(static fn(OutboxEntry $entry): string => $entry->event->type(), $harness->outbox->pending(10));
    }
}
