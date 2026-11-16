<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory;

use Override;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
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
 * Process-local {@see SubmissionRepository} for tests and demos.
 *
 * Identity semantics: like a database, it stores a snapshot. {@see self::add()}, {@see self::save()} and
 * {@see self::claim()} copy the aggregate (without its pending domain events, which are never persisted) and every read
 * returns a new copy, so a change is only visible once saved.
 *
 * Lists keep the insertion order on ties (stable sorts).
 *
 * Implements the whole repository port (one method per query), hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class InMemorySubmissionRepository implements SubmissionRepository
{
    /** @var array<string, Submission> stored snapshots by submission id, in insertion order */
    private array $submissions = [];

    /**
     * Copies of every stored submission, in insertion order (monitoring, tests).
     *
     * @return list<Submission>
     */
    public function all(): array
    {
        return $this->selection()->copies();
    }

    #[Override]
    public function add(Submission $submission): void
    {
        if ($this->findByExternalReference($submission->tenantId(), $submission->externalReference()) instanceof Submission) {
            throw InvariantViolation::because(
                'submission.duplicate_external_reference',
                'A submission already exists for this external reference.',
                ['external_reference' => $submission->externalReference()->value()],
            );
        }

        $this->store($submission);
    }

    #[Override]
    public function save(Submission $submission): void
    {
        $this->store($submission);
    }

    #[Override]
    public function get(TenantId $tenantId, SubmissionId $id): Submission
    {
        $stored = $this->submissions[$id->value()] ?? null;
        if (!$stored instanceof Submission || !$stored->tenantId()->equals($tenantId)) {
            throw RecordNotFound::withIdentifier('submission', $id->value());
        }

        return clone $stored;
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?Submission
    {
        return $this->ofTenant($tenantId)
            ->where(static fn(Submission $submission): bool => $submission->externalReference()->equals($externalReference))
            ->copies()[0] ?? null;
    }

    #[Override]
    public function claim(Submission $reclaimed, SubmissionState $expectedStoredState): bool
    {
        $stored = $this->submissions[$reclaimed->id()->value()] ?? null;
        if (!$stored instanceof Submission || $stored->state() !== $expectedStoredState) {
            return false;
        }

        $this->store($reclaimed);

        return true;
    }

    #[Override]
    public function dueForPolling(TenantId $tenantId, Instant $now, PollingWindow $window, int $limit): array
    {
        // Never polled first, then least recently polled; stable, so ties keep the insertion order.
        return $this->ofTenant($tenantId)
            ->where(static fn(Submission $submission): bool => $submission->needsPolling($now, $window))
            ->sortedBy(
                static fn(Submission $a, Submission $b): int => Selection::compareInstants($a->lastPolledAt(), $b->lastPolledAt()),
            )
            ->limit($limit)
            ->copies();
    }

    #[Override]
    public function unresolved(TenantId $tenantId, int $limit): array
    {
        return $this->ofTenant($tenantId)
            ->where(static fn(Submission $submission): bool => \in_array(
                $submission->state(),
                [SubmissionState::Uploading, SubmissionState::OutcomeUnknown],
                true,
            ))
            ->sortedBy(
                static fn(Submission $a, Submission $b): int => Selection::compareInstants($a->updatedAt(), $b->updatedAt()),
            )
            ->limit($limit)
            ->copies();
    }

    #[Override]
    public function occupiesDeclarationSlot(TenantId $tenantId, DeclarationType $type, SubmissionId $except): bool
    {
        return $this->ofTenant($tenantId)
            ->where(
                static fn(Submission $submission): bool => $submission->identity()->declarationType() === $type
                    && !$submission->id()->equals($except)
                    && $submission->state()->occupiesDeclarationSlot(),
            )
            ->all() !== [];
    }

    #[Override]
    public function isUrssafReferenceKnown(TenantId $tenantId, SubmissionKind $kind, UrssafReference $reference): bool
    {
        return $this->ofTenant($tenantId)
            ->where(
                static fn(Submission $submission): bool => $submission->identity()->kind() === $kind
                    && $submission->urssafReference()?->equals($reference) === true,
            )
            ->all() !== [];
    }

    #[Override]
    public function inState(TenantId $tenantId, ?SubmissionState $state, int $limit, int $offset): array
    {
        // Most recent first; the most recently inserted first on ties (reversed before the stable sort).
        return $this->ofTenant($tenantId)
            ->where(static fn(Submission $submission): bool => !$state instanceof SubmissionState || $submission->state() === $state)
            ->reversed()
            ->sortedBy(
                static fn(Submission $a, Submission $b): int => Selection::compareInstants($b->createdAt(), $a->createdAt()),
            )
            ->skip($offset)
            ->limit($limit)
            ->copies();
    }

    /**
     * Stores a snapshot of the submission, without its pending events.
     */
    private function store(Submission $submission): void
    {
        $snapshot = clone $submission;
        $snapshot->releaseEvents();
        $this->submissions[$submission->id()->value()] = $snapshot;
    }

    /**
     * @return Selection<Submission>
     */
    private function ofTenant(TenantId $tenantId): Selection
    {
        return $this->selection()->where(
            static fn(Submission $submission): bool => $submission->tenantId()->equals($tenantId),
        );
    }

    /**
     * The stored submissions in insertion order.
     *
     * @return Selection<Submission>
     */
    private function selection(): Selection
    {
        return Selection::of($this->submissions);
    }
}
