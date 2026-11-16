<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineSubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\HostNote;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence\SubmissionBuilder;

/**
 * Source: reference package (DoctrineTransactionManagerTest).
 */
final class DoctrineTransactionManagerTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineSubmissionRepository $repository;

    private DoctrineTransactionManager $transactions;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->repository = new DoctrineSubmissionRepository($this->entityManager);
        $this->transactions = new DoctrineTransactionManager($this->entityManager);
    }

    public function testItCommitsAndReturnsTheResultOfTheOperation(): void
    {
        $connection = $this->entityManager->getConnection();

        $result = $this->transactions->transactional(function () use ($connection): string {
            $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

            return 'done:' . ($connection->isTransactionActive() ? 'in' : 'out');
        });

        static::assertSame('done:in', $result);
        static::assertFalse($connection->isTransactionActive());
        $this->entityManager->clear();
        static::assertSame('s-1', $this->repository->get(TenantId::fromString('tenant-1'), SubmissionId::fromString('s-1'))->id()->value());
    }

    public function testItRethrowsTheSameThrowable(): void
    {
        $failure = new LogicException('boom');

        $this->expectExceptionObject($failure);
        $this->transactions->transactional(static function () use ($failure): never {
            throw $failure;
        });
    }

    public function testItRollsBackAndDetachesThePackageEntitiesWhenTheOperationFails(): void
    {
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $other = SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-2', '2026-03-01T00:00:00Z');

        try {
            $this->transactions->transactional(function () use ($submission, $other): never {
                $this->repository->add($submission);
                $this->repository->add($other);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        static::assertTrue($this->entityManager->isOpen());
        static::assertFalse($this->entityManager->contains($submission));
        static::assertFalse($this->entityManager->contains($other));
        $managed = 0;
        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap() as $entities) {
            $managed += \count($entities);
        }
        static::assertSame(0, $managed);
        $this->expectException(RecordNotFound::class);
        $this->repository->get(TenantId::fromString('tenant-1'), SubmissionId::fromString('s-1'));
    }

    public function testItKeepsTheEntitiesOfTheHostApplicationManagedWhenTheOperationFails(): void
    {
        $entityManager = TestDatabase::sharedEntityManager();
        $repository = new DoctrineSubmissionRepository($entityManager);
        $transactions = new DoctrineTransactionManager($entityManager);
        $note = new HostNote('flushed before');
        $entityManager->persist($note);
        $entityManager->flush();
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $note->rewrite('changed, not flushed yet');

        try {
            $transactions->transactional(static function () use ($repository, $submission): never {
                $repository->add($submission);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertFalse($entityManager->contains($submission));
        static::assertTrue($entityManager->contains($note));
        static::assertSame($note, $entityManager->find(HostNote::class, $note->id()));
        static::assertSame('changed, not flushed yet', $note->text());
    }

    public function testNestedOperationsJoinTheOutermostTransaction(): void
    {
        try {
            $this->transactions->transactional(function (): never {
                $this->transactions->transactional(function (): void {
                    $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));
                });

                throw new LogicException('outer failure');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        $this->expectException(RecordNotFound::class);
        $this->repository->get(TenantId::fromString('tenant-1'), SubmissionId::fromString('s-1'));
    }
}
