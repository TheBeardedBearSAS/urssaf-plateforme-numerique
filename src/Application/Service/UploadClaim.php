<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Service;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\SubmissionAlreadyRecorded;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * First step of {@see SubmissionUploader}, run in its transaction: the record of the external reference is created in
 * "uploading", or the existing one is handled by the re-entry table of ADR 0004:
 * - other fingerprint -> {@see SubmissionAlreadyRecorded};
 * - "uploading" -> {@see UploadOutcome::NotClaimed} (another process is sending it);
 * - known to URSSAF or "outcome_unknown" -> {@see UploadOutcome::Replayed};
 * - reclaimable -> reclaimed (atomic claim, one more attempt; lost claim -> {@see UploadOutcome::NotClaimed}, reported
 *   as stored) until the policy maximum of attempts ({@see UploadOutcome::Exhausted}).
 * A record created concurrently (duplicate external reference on insert) is read again and handled by the same table.
 * Before any write, a declaration is refused while another one of the same type is in process (ADR 0008, unless the
 * policy disables the guard).
 */
final readonly class UploadClaim
{
    public function __construct(
        private SubmissionRepository $submissions,
        private Clock $clock,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * The record to send, in "uploading" and stored, or the report when nothing must be sent.
     *
     * @throws SubmissionAlreadyRecorded when the external reference is recorded with another file
     * @throws InvariantViolation        "submission.declaration_in_process" (ADR 0008)
     */
    public function claim(SubmissionIdentity $candidate): Submission|UploadReport
    {
        $existing = $this->submissions->findByExternalReference($candidate->tenantId(), $candidate->externalReference());
        if ($existing instanceof Submission) {
            return $this->reenter($existing, $candidate);
        }

        return $this->create($candidate);
    }

    private function create(SubmissionIdentity $candidate): Submission|UploadReport
    {
        $this->guard($candidate);
        $submission = Submission::start($candidate, $this->now());
        try {
            $this->submissions->add($submission);
        } catch (InvariantViolation $duplicate) {
            // Created concurrently by another process: handled as a re-entry.
            $existing = $this->submissions->findByExternalReference($candidate->tenantId(), $candidate->externalReference())
                ?? throw $duplicate;

            return $this->reenter($existing, $candidate);
        }

        return $submission;
    }

    /**
     * The re-entry table of ADR 0004.
     */
    private function reenter(Submission $existing, SubmissionIdentity $candidate): Submission|UploadReport
    {
        if (!$existing->matchesFingerprint($candidate->fingerprint())) {
            throw SubmissionAlreadyRecorded::withExternalReference($candidate->externalReference(), $existing->id());
        }
        $state = $existing->state();
        if ($state === SubmissionState::Uploading) {
            return UploadReport::of($existing, UploadOutcome::NotClaimed);
        }
        if (!$state->isReclaimable()) {
            return UploadReport::of($existing, UploadOutcome::Replayed);
        }
        $maxAttempts = $this->policy->maxUploadAttempts();
        if (!$existing->canReclaim($maxAttempts)) {
            return UploadReport::of($existing, UploadOutcome::Exhausted);
        }
        $this->guard($existing->identity());
        $existing->reclaim($this->now(), $maxAttempts);
        if (!$this->submissions->claim($existing, $state)) {
            return UploadReport::of($this->submissions->get($existing->tenantId(), $existing->id()), UploadOutcome::NotClaimed);
        }

        return $existing;
    }

    /**
     * URSSAF processes one declaration of each type at a time (ADR 0008).
     *
     * @throws InvariantViolation "submission.declaration_in_process"
     */
    private function guard(SubmissionIdentity $identity): void
    {
        $type = $identity->declarationType();
        if (!$type instanceof DeclarationType || !$this->policy->concurrentDeclarationGuard()) {
            return;
        }
        if ($this->submissions->occupiesDeclarationSlot($identity->tenantId(), $type, $identity->id())) {
            throw InvariantViolation::because(
                'submission.declaration_in_process',
                \sprintf('Another submission of type %s is in process for the tenant: URSSAF processes one at a time.', $type->value),
                ['declaration_type' => $type->value],
            );
        }
    }

    private function now(): Instant
    {
        return Instant::fromDateTime($this->clock->now());
    }
}
