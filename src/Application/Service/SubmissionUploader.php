<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Service;

use Closure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\SubmissionAlreadyRecorded;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use Throwable;

/**
 * Synchronous upload shared by the upload handlers (ADR 0004):
 *
 * 1. the file is fingerprinted (streamed once) and the platform of the tenant resolved;
 * 2. in a first transaction, the record is created or reclaimed in "uploading" ({@see UploadClaim}: re-entry table,
 *    concurrency guard of the declarations), or the report is returned when nothing must be sent;
 * 3. the file is sent, outside any transaction;
 * 4. in a second transaction, the outcome is recorded: in_process ({@see UploadOutcome::Uploaded}), upload_rejected
 *    with the error codes and messages ({@see UploadOutcome::Rejected}), invalid_credentials
 *    ({@see UploadOutcome::InvalidCredentials}) or outcome_unknown ({@see UploadOutcome::OutcomeUnknown}, never sent
 *    again automatically, ADR 0003). A temporary failure ({@see RetryableException}: the gateway raises
 *    {@see OutcomeUnknown} instead whenever the call may have reached URSSAF) moves the record to "error" and is
 *    thrown as {@see RetryableUrssafFailure}.
 *
 * A temporary failure that may have reached URSSAF is treated as {@see OutcomeUnknown}, whatever the gateway raised
 * (defense in depth: a resend could create a duplicate). Any other failure of the call is rethrown after the record
 * is left in a reclaimable or reviewable state: an answer the contract does not describe, or missing credentials
 * ({@see UrssafException}: nothing was processed) moves it to "error"; any other throwable (the send is uncertain, no
 * automatic resend, ADR 0003) moves it to "outcome_unknown". Neither leaves the record "uploading", which would hold
 * the declaration slot (ADR 0008).
 */
final readonly class SubmissionUploader
{
    private UploadClaim $claims;

    public function __construct(
        private SubmissionRepository $submissions,
        private PlatformGateway $gateway,
        private PlatformResolver $platforms,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        WorkflowPolicy $policy,
    ) {
        $this->claims = new UploadClaim($submissions, $clock, $policy);
    }

    /**
     * @param ?DeclarationType $type non-null exactly for declarations
     *
     * @throws SubmissionAlreadyRecorded when the external reference is recorded with another file
     * @throws InvariantViolation        "submission.declaration_in_process" (ADR 0008)
     * @throws RetryableUrssafFailure    after a temporary failure (the record is moved to "error")
     */
    public function upload(
        TenantId $tenantId,
        ExternalReference $externalReference,
        SubmissionKind $kind,
        ?DeclarationType $type,
        FileSource $file,
    ): UploadReport {
        $candidate = new SubmissionIdentity(
            SubmissionId::fromString($this->ids->generate()),
            $tenantId,
            $this->platforms->forTenant($tenantId),
            $externalReference,
            $kind,
            $type,
            $file->name(),
            FileFingerprinter::fingerprint($file, $kind, $type),
        );
        $claimed = $this->unitOfWork->run(fn(): Submission|UploadReport => $this->claims->claim($candidate));

        return $claimed instanceof UploadReport ? $claimed : $this->deliver($claimed, $file);
    }

    /**
     * Sends the claimed record, then records the outcome in its own transaction.
     *
     * @throws RetryableUrssafFailure
     */
    private function deliver(Submission $claimed, FileSource $file): UploadReport
    {
        try {
            $reference = $this->send($claimed, $file);
        } catch (UrssafRequestRejected $rejection) {
            return $this->settleRejected($claimed, $rejection);
        } catch (UrssafAuthenticationFailed) {
            return $this->settleInvalidCredentials($claimed);
        } catch (OutcomeUnknown) {
            return $this->settleUnknown($claimed);
        } catch (RetryableException $failure) {
            if ($failure instanceof UrssafUnavailable && $failure->mayHaveReachedUrssaf()) {
                return $this->settleUnknown($claimed);
            }
            $this->recordFailed($claimed);

            throw RetryableUrssafFailure::causedBy($failure);
        } catch (UrssafException $failure) {
            $this->recordFailed($claimed);

            throw $failure;
        } catch (Throwable $failure) {
            $this->settleUnknown($claimed);

            throw $failure;
        }

        return $this->settle($claimed, UploadOutcome::Uploaded, static function (Submission $submission, Instant $now) use ($reference): void {
            $submission->markUploaded($reference, $now);
        });
    }

    private function settleInvalidCredentials(Submission $claimed): UploadReport
    {
        return $this->settle($claimed, UploadOutcome::InvalidCredentials, static function (Submission $submission, Instant $now): void {
            $submission->markInvalidCredentials($now);
        });
    }

    /**
     * @param Closure(Submission, Instant): void $transition
     * @param-immediately-invoked-callable $transition
     */
    private function settle(Submission $claimed, UploadOutcome $outcome, Closure $transition): UploadReport
    {
        $this->record($claimed, $transition);

        return UploadReport::of($claimed, $outcome);
    }

    private function settleRejected(Submission $claimed, UrssafRequestRejected $rejection): UploadReport
    {
        $errors = array_map(
            static fn(ApiError $error): SubmissionError => SubmissionError::of($error->code(), $error->message()),
            $rejection->errors()->all(),
        );

        return $this->settle($claimed, UploadOutcome::Rejected, static function (Submission $submission, Instant $now) use ($errors): void {
            $submission->markUploadRejected($errors, $now);
        });
    }

    private function settleUnknown(Submission $claimed): UploadReport
    {
        return $this->settle($claimed, UploadOutcome::OutcomeUnknown, static function (Submission $submission, Instant $now): void {
            $submission->markOutcomeUnknown($now);
        });
    }

    private function recordFailed(Submission $claimed): void
    {
        $this->record($claimed, static function (Submission $submission, Instant $now): void {
            $submission->markFailed($now);
        });
    }

    private function send(Submission $submission, FileSource $file): UrssafReference
    {
        $identity = $submission->identity();
        $type = $identity->declarationType();

        return $type instanceof DeclarationType
            ? $this->gateway->uploadDeclaration($identity->tenantId(), $identity->platformId(), $type, $file)
            : $this->gateway->uploadIdentification($identity->tenantId(), $identity->platformId(), $file);
    }

    /**
     * Records the outcome of the call in its own transaction.
     *
     * @param Closure(Submission, Instant): void $transition
     * @param-immediately-invoked-callable $transition
     */
    private function record(Submission $submission, Closure $transition): void
    {
        $this->unitOfWork->run(function (RecordedEvents $events) use ($submission, $transition): void {
            $transition($submission, Instant::fromDateTime($this->clock->now()));
            $this->submissions->save($submission);
            $events->record(...$submission->releaseEvents());
        });
    }
}
