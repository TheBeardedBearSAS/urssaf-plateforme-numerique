<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use Closure;
use DateInterval;
use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadDeclaration;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadDeclarationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\FileFingerprinter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\SubmissionUploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\StreamFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\SubmissionSamples;

/**
 * Shared steps of the upload tests: the handlers over the harness, the uploaded file and the stored records. The first
 * upload of a tenant gets the local id "id-1" from the sequential generator.
 */
final class UploadScenario
{
    public const REFERENCE = 'host-ref-1';
    public const FILE = 'identification-2026-01.csv';
    public const FIRST_ID = 'id-1';

    public static function policy(int $maxUploadAttempts): WorkflowPolicy
    {
        return new WorkflowPolicy(
            new DateInterval('PT10M'),
            new DateInterval('PT6H'),
            new DateInterval('P1Y'),
            $maxUploadAttempts,
        );
    }

    public static function file(string $contents): StreamFileSource
    {
        return StreamFileSource::named(self::FILE, $contents);
    }

    public static function uploaderOver(Harness $harness, SubmissionRepository $submissions): SubmissionUploader
    {
        return new SubmissionUploader(
            $submissions,
            $harness->gateway,
            $harness->platforms,
            $harness->ids,
            $harness->clock,
            $harness->unitOfWork,
            $harness->policy,
        );
    }

    public static function identification(Harness $harness, StreamFileSource $file): UploadReport
    {
        return self::identificationThrough(self::uploaderOver($harness, $harness->submissions), $file);
    }

    public static function identificationThrough(SubmissionUploader $uploader, StreamFileSource $file): UploadReport
    {
        return (new UploadIdentificationHandler($uploader))(UploadIdentification::of(
            Harness::tenant(),
            ExternalReference::fromString(self::REFERENCE),
            $file,
        ));
    }

    public static function declaration(Harness $harness, DeclarationType $type, StreamFileSource $file): UploadReport
    {
        return (new UploadDeclarationHandler(self::uploaderOver($harness, $harness->submissions)))(UploadDeclaration::of(
            Harness::tenant(),
            ExternalReference::fromString(self::REFERENCE),
            $type,
            $file,
        ));
    }

    public static function stored(Harness $harness, string $id): Submission
    {
        return $harness->submissions->get(Harness::tenant(), SubmissionId::fromString($id));
    }

    /**
     * Changes the stored record through the aggregate (another use case), its events dropped.
     *
     * @param Closure(Submission, Instant): void $change
     * @param-immediately-invoked-callable $change
     */
    public static function change(Harness $harness, string $id, Closure $change): void
    {
        $submission = self::stored($harness, $id);
        $change($submission, Harness::at(Harness::NOW));
        $submission->releaseEvents();
        $harness->submissions->save($submission);
    }

    /**
     * A harness whose tenant uploaded the identification "content" once (local id "id-1"), now in the given state.
     * The journal is cleared.
     */
    public static function recorded(SubmissionState $state): Harness
    {
        if ($state === SubmissionState::Uploading) {
            return self::uploading();
        }
        $harness = new Harness();
        $first = match ($state) {
            SubmissionState::InProcess, SubmissionState::Accepted, SubmissionState::Rejected => [SubmissionSamples::urssafReference(1)],
            SubmissionState::UploadRejected => [UrssafRequestRejected::withErrors(Operation::UploadIdentification, 400, ApiErrors::none())],
            SubmissionState::Error => [UrssafUnavailable::withStatus(Operation::UploadIdentification, 429, null, ApiErrors::none())],
            SubmissionState::InvalidCredentials => [UrssafAuthenticationFailed::withStatus(403)],
            SubmissionState::OutcomeUnknown, SubmissionState::NotReceived => [OutcomeUnknown::afterStatus(Operation::UploadIdentification, 500)],
        };
        $harness->gateway->willUploadIdentification(...$first);
        try {
            self::identification($harness, self::file('content'));
        } catch (RetryableUrssafFailure $expected) {
            // Error: the temporary failure.
            Assert::assertSame(SubmissionState::Error, $state, $expected->getMessage());
        }
        if ($state === SubmissionState::Accepted || $state === SubmissionState::Rejected) {
            self::change($harness, self::FIRST_ID, static function (Submission $submission, Instant $now) use ($state): void {
                $submission->applySnapshot(SubmissionSamples::snapshot(strtoupper($state->value), null), $now);
            });
        }
        if ($state === SubmissionState::NotReceived) {
            self::change($harness, self::FIRST_ID, static function (Submission $submission, Instant $now): void {
                $submission->markNotReceived($now);
            });
        }
        Assert::assertSame($state, self::stored($harness, self::FIRST_ID)->state());
        $harness->journal->clear();

        return $harness;
    }

    /**
     * A harness whose tenant started uploading the identification "content" (local id "id-1"), the file being sent by
     * another process: the record is "uploading", nothing reached the gateway. The journal is empty.
     */
    public static function uploading(): Harness
    {
        $harness = new Harness();
        $file = self::file('content');
        $harness->submissions->save(Submission::start(
            new SubmissionIdentity(
                SubmissionId::fromString(self::FIRST_ID),
                Harness::tenant(),
                Harness::platform(),
                ExternalReference::fromString(self::REFERENCE),
                SubmissionKind::Identification,
                null,
                $file->name(),
                FileFingerprinter::fingerprint($file, SubmissionKind::Identification, null),
            ),
            Harness::at(Harness::NOW),
        ));

        return $harness;
    }
}
