<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Override;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\SubmissionErrorsType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\SubmissionIdType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\SubmissionSnapshotType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\UrssafReferenceType;
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
 * Doctrine ORM implementation of {@see SubmissionRepository} (after the reference repositories, ADR 0011), with the
 * semantics of the in-memory one: every read is scoped by tenant, duplicates are reported as {@see InvariantViolation}
 * with the same codes, lists end with deterministic tie-breakers (creation instant, then id).
 *
 * Unit of work: {@see self::add()} and {@see self::save()} flush immediately (a flush writes every dirty managed
 * entity), so inside a {@see DoctrineTransactionManager} transaction a write is durable only when that transaction
 * commits. Reads return the managed instance: unlike the in-memory repository, a change is visible to every holder of
 * the instance before it is saved.
 *
 * Duplicates: add() checks the external reference of the tenant, then the id, before inserting, so that the usual
 * duplicate is refused on a healthy connection and entity manager (the Application reads the existing submission
 * again). The insert runs in its own (nested) transaction, a savepoint inside an outer one: a concurrent writer that
 * wins the race after the check makes the flush fail; the savepoint is rolled back (so the outer transaction stays
 * usable, on PostgreSQL too) and the violation is reported with the same code, but the failed flush has closed the
 * entity manager (ORM 3): later flushes of the same entity manager fail until the host resets it.
 *
 * Optimistic concurrency (ADR 0011): the submission carries a version. Saving a managed instance that another process
 * changed since it was read fails with "submission.concurrent_modification". {@see self::claim()} is one conditional
 * UPDATE (expected state and read version) that writes the reclaimed fields and bumps the version; the claimed instance
 * is then detached (as any managed copy of the row) and carries the new version, so that its next save() is the same
 * conditional UPDATE on the version. Saving an unmanaged instance of a stored submission works the same way (and drops
 * the managed copy of the row, if any).
 *
 * Polling: {@see self::dueForPolling()} evaluates {@see Submission::needsPolling()} in SQL on the real columns (state,
 * kind, raw status and payment status, instants). "Instant + interval <= now" is evaluated as
 * "instant <= now - interval"; it differs from the domain rule only for month-based intervals across month ends.
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness and write helpers, hence the
 * method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class DoctrineSubmissionRepository implements SubmissionRepository
{
    private const ENTITY = Submission::class;

    private const CONCURRENT_MODIFICATION = 'submission.concurrent_modification';

    private const DUE_FOR_POLLING = ' s.identity.tenantId = :tenant AND s.createdAt >= :createdSince'
        . ' AND (s.lastPoll.rawStatus IS NULL OR s.lastPoll.rawStatus <> :accepted OR s.lastPoll.rawPaymentStatus IS NULL OR s.lastPoll.rawPaymentStatus <> :settledPayment)'
        . ' AND ((s.state = :inProcess AND (s.lastPoll.polledAt IS NULL OR s.lastPoll.polledAt <= :statusPolledUntil))'
        . ' OR (s.state IN (:processed) AND s.identity.kind = :declaration AND (s.lastPoll.polledAt IS NULL OR s.lastPoll.polledAt <= :paymentPolledUntil)))';

    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @throws InvariantViolation "submission.duplicate_external_reference" or "submission.duplicate_id"
     */
    #[Override]
    public function add(Submission $submission): void
    {
        $this->assertNew($submission);
        try {
            $this->entityManager->getConnection()->transactional(function () use ($submission): void {
                $this->entityManager->persist($submission);
                $this->entityManager->flush();
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent writer won the race between the check above and the flush.
            $this->assertNew($submission);

            throw $exception;
        }
    }

    /**
     * @throws InvariantViolation "submission.concurrent_modification"
     */
    #[Override]
    public function save(Submission $submission): void
    {
        if ($this->entityManager->contains($submission)) {
            $this->saveManaged($submission);

            return;
        }
        $this->saveUnmanaged($submission);
    }

    #[Override]
    public function get(TenantId $tenantId, SubmissionId $id): Submission
    {
        $submission = $this->entityManager->find(self::ENTITY, $id);
        if (!$submission instanceof Submission || !$submission->tenantId()->equals($tenantId)) {
            throw RecordNotFound::withIdentifier('submission', $id->value());
        }

        return $submission;
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?Submission
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery('SELECT s FROM ' . self::ENTITY . ' s WHERE s.identity.tenantId = :tenant AND s.identity.externalReference = :reference')
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('reference', $externalReference, ExternalReferenceType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function claim(Submission $reclaimed, SubmissionState $expectedStoredState): bool
    {
        $claimed = $this->write($reclaimed, [$expectedStoredState->value]);
        // Whatever the outcome, the managed copy (if any, possibly the reclaimed instance itself) no longer matches the
        // row: drop it so that the next read loads the stored submission.
        $this->detach($reclaimed->id());

        return $claimed;
    }

    #[Override]
    public function dueForPolling(TenantId $tenantId, Instant $now, PollingWindow $window, int $limit): array
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT s, CASE WHEN s.lastPoll.polledAt IS NULL THEN 0 ELSE 1 END AS HIDDEN polledRank FROM ' . self::ENTITY . ' s'
                . ' WHERE' . self::DUE_FOR_POLLING
                . ' ORDER BY polledRank ASC, s.lastPoll.polledAt ASC, s.createdAt ASC, s.id ASC',
            )
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->setMaxResults($limit);
        foreach (PollingParameters::of($now, $window) as $name => [$value, $type]) {
            $query->setParameter($name, $value, $type);
        }

        return Hydration::all($query->toIterable(), self::ENTITY);
    }

    #[Override]
    public function unresolved(TenantId $tenantId, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT s FROM ' . self::ENTITY . ' s WHERE s.identity.tenantId = :tenant AND s.state IN (:states)'
                    . ' ORDER BY s.updatedAt ASC, s.createdAt ASC, s.id ASC',
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter(
                    'states',
                    [SubmissionState::Uploading->value, SubmissionState::OutcomeUnknown->value],
                    ArrayParameterType::STRING,
                )
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function occupiesDeclarationSlot(TenantId $tenantId, DeclarationType $type, SubmissionId $except): bool
    {
        return $this->entityManager
            ->createQuery(
                'SELECT s.id FROM ' . self::ENTITY . ' s WHERE s.identity.tenantId = :tenant AND s.identity.declarationType = :type'
                . ' AND s.id <> :except AND s.state IN (:states)',
            )
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->setParameter('type', $type->value)
            ->setParameter('except', $except, SubmissionIdType::NAME)
            ->setParameter(
                'states',
                StateCodes::where(static fn(SubmissionState $state): bool => $state->occupiesDeclarationSlot()),
                ArrayParameterType::STRING,
            )
            ->getScalarResult() !== [];
    }

    #[Override]
    public function isUrssafReferenceKnown(TenantId $tenantId, SubmissionKind $kind, UrssafReference $reference): bool
    {
        return $this->entityManager
            ->createQuery(
                'SELECT s.id FROM ' . self::ENTITY . ' s WHERE s.identity.tenantId = :tenant AND s.identity.kind = :kind'
                . ' AND s.urssafReference = :reference',
            )
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->setParameter('kind', $kind->value)
            ->setParameter('reference', $reference, UrssafReferenceType::NAME)
            ->getScalarResult() !== [];
    }

    #[Override]
    public function inState(TenantId $tenantId, ?SubmissionState $state, int $limit, int $offset): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT s FROM ' . self::ENTITY . ' s WHERE s.identity.tenantId = :tenant AND s.state IN (:states)'
                    . ' ORDER BY s.createdAt DESC, s.id DESC',
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('states', $state instanceof SubmissionState ? [$state->value] : StateCodes::all(), ArrayParameterType::STRING)
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    /**
     * @throws InvariantViolation "submission.concurrent_modification"
     */
    private function saveManaged(Submission $submission): void
    {
        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            // The domain exception carries no previous throwable: the Doctrine one only says that no row matched.
            $this->refuseConcurrentModification($submission);
        }
    }

    /**
     * A submission the entity manager does not manage: inserted when new, otherwise written by the conditional UPDATE
     * on its version (and the managed copy of the row, read before and now stale, is dropped).
     *
     * @throws InvariantViolation "submission.concurrent_modification"
     */
    private function saveUnmanaged(Submission $submission): void
    {
        if (!$this->isStored($submission->id())) {
            $this->entityManager->persist($submission);
            $this->entityManager->flush();

            return;
        }
        $written = $this->write($submission, StateCodes::all());
        $this->detach($submission->id());
        if (!$written) {
            $this->refuseConcurrentModification($submission);
        }
    }

    /**
     * Writes the mutable fields of the submission with one UPDATE conditioned on the version it carries and on the
     * stored state, bumping the version; on success the instance carries the new version.
     *
     * @param list<string> $storedStates the codes of the states the stored submission may be in
     *
     * @return bool false when no row matched (changed by another process, or not in an expected state)
     */
    private function write(Submission $submission, array $storedStates): bool
    {
        $metadata = $this->entityManager->getClassMetadata(self::ENTITY);
        /** @var int $version the mapped "version" field of the aggregate */
        $version = $metadata->getFieldValue($submission, 'version');
        $query = $this->entityManager
            ->createQuery(
                'UPDATE ' . self::ENTITY . ' s SET s.state = :state, s.attempts = :attempts, s.urssafReference = :urssafReference,'
                . ' s.lastPoll.rawStatus = :rawStatus, s.lastPoll.rawPaymentStatus = :rawPaymentStatus, s.snapshot = :snapshot, s.errors = :errors,'
                . ' s.updatedAt = :updatedAt, s.lastPoll.polledAt = :lastPolledAt, s.version = s.version + 1'
                . ' WHERE s.id = :id AND s.version = :version AND s.state IN (:storedStates)',
            )
            ->setParameter('state', $submission->state()->value)
            ->setParameter('attempts', $submission->attempts())
            ->setParameter('urssafReference', $submission->urssafReference(), UrssafReferenceType::NAME)
            ->setParameter('rawStatus', $metadata->getFieldValue($submission, 'lastPoll.rawStatus'))
            ->setParameter('rawPaymentStatus', $metadata->getFieldValue($submission, 'lastPoll.rawPaymentStatus'))
            ->setParameter('snapshot', $submission->snapshot(), SubmissionSnapshotType::NAME)
            ->setParameter('errors', $submission->errors(), SubmissionErrorsType::NAME)
            ->setParameter('updatedAt', $submission->updatedAt(), InstantType::NAME)
            ->setParameter('lastPolledAt', $submission->lastPolledAt(), InstantType::NAME)
            ->setParameter('id', $submission->id(), SubmissionIdType::NAME)
            ->setParameter('version', $version)
            ->setParameter('storedStates', $storedStates, ArrayParameterType::STRING);
        if ($query->execute() !== 1) {
            return false;
        }
        $metadata->setFieldValue($submission, 'version', $version + 1);

        return true;
    }

    /**
     * @throws InvariantViolation always
     */
    private function refuseConcurrentModification(Submission $submission): never
    {
        throw InvariantViolation::because(
            self::CONCURRENT_MODIFICATION,
            'The submission was changed by another process since it was read: read it again.',
            ['id' => $submission->id()->value()],
        );
    }

    private function detach(SubmissionId $id): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $managed = $unitOfWork->tryGetById($id, self::ENTITY);
        if ($managed !== false) {
            $unitOfWork->detach($managed);
        }
    }

    private function isStored(SubmissionId $id): bool
    {
        return $this->entityManager
            ->createQuery('SELECT s.id FROM ' . self::ENTITY . ' s WHERE s.id = :id')
            ->setParameter('id', $id, SubmissionIdType::NAME)
            ->getScalarResult() !== [];
    }

    /**
     * Same checks, in the same order, as the in-memory repository.
     *
     * @throws InvariantViolation
     */
    private function assertNew(Submission $submission): void
    {
        if ($this->findByExternalReference($submission->tenantId(), $submission->externalReference()) instanceof Submission) {
            throw InvariantViolation::because(
                'submission.duplicate_external_reference',
                'A submission already exists for this external reference.',
                ['external_reference' => $submission->externalReference()->value()],
            );
        }
        if ($this->isStored($submission->id())) {
            throw InvariantViolation::because(
                'submission.duplicate_id',
                'A submission already exists with this id.',
                ['id' => $submission->id()->value()],
            );
        }
    }
}
