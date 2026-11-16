<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PollingWindow;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Persistence of the {@see Submission} aggregate. Every read and write is scoped by tenant.
 */
interface SubmissionRepository
{
    /**
     * Stores a new submission.
     *
     * @throws InvariantViolation "submission.duplicate_external_reference" when the tenant already has a submission
     *                            with this external reference, then "submission.duplicate_id" when a submission
     *                            (of any tenant) already has this id
     */
    public function add(Submission $submission): void;

    /**
     * Stores the changes of an existing submission.
     *
     * @throws InvariantViolation `submission.concurrent_modification` when another process changed it meanwhile (ADR 0011)
     */
    public function save(Submission $submission): void;

    /**
     * @throws RecordNotFound "submission.not_found"
     */
    public function get(TenantId $tenantId, SubmissionId $id): Submission;

    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?Submission;

    /**
     * Persists a reclaimed submission only if the stored one is still in the expected state (atomic conditional
     * update), so that two workers cannot claim the same submission.
     *
     * @return bool true when the submission was persisted, false when the stored state was no longer the expected one
     */
    public function claim(Submission $reclaimed, SubmissionState $expectedStoredState): bool;

    /**
     * The submissions of the tenant matching {@see Submission::needsPolling()} for $now and $window (in process, or
     * declarations whose payment is not settled, not older than the maximum polling age), the least recently polled
     * first (never polled first).
     *
     * @param positive-int $limit
     *
     * @return list<Submission>
     */
    public function dueForPolling(TenantId $tenantId, Instant $now, PollingWindow $window, int $limit): array;

    /**
     * The submissions whose outcome is not settled (states "uploading" and "outcome_unknown"), the oldest update first.
     *
     * @param positive-int $limit
     *
     * @return list<Submission>
     */
    public function unresolved(TenantId $tenantId, int $limit): array;

    /**
     * Whether another submission of the tenant (not $except) with this declaration type is in a state for which
     * {@see SubmissionState::occupiesDeclarationSlot()} is true (uploading, in_process, outcome_unknown): URSSAF
     * processes one declaration of each type at a time (ADR 0008).
     */
    public function occupiesDeclarationSlot(TenantId $tenantId, DeclarationType $type, SubmissionId $except): bool;

    /**
     * Whether a submission of the tenant and kind already holds this URSSAF reference.
     */
    public function isUrssafReferenceKnown(TenantId $tenantId, SubmissionKind $kind, UrssafReference $reference): bool;

    /**
     * @param positive-int     $limit
     * @param non-negative-int $offset
     *
     * @return list<Submission> the most recent first; every state when $state is null
     */
    public function inState(TenantId $tenantId, ?SubmissionState $state, int $limit, int $offset): array;
}
