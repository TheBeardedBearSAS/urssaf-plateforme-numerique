<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UploadClaim;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineSubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\BeforeNextSavepoint;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence\SubmissionBuilder;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence\SubmissionRepositoryContract;

/**
 * The shared submission repository contract and the Doctrine-specific scenarios, on in-memory SQLite: one method per
 * scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class DoctrineSubmissionRepositoryTest extends TestCase
{
    use SubmissionRepositoryContract {
        setUp as private contractSetUp;
    }

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->contractSetUp();
    }

    protected function createRepository(): SubmissionRepository
    {
        return new DoctrineSubmissionRepository($this->entityManager);
    }

    protected function startNewUnitOfWork(): void
    {
        $this->entityManager->clear();
    }

    public function testItReturnsTheManagedInstanceUntilTheEntityManagerIsCleared(): void
    {
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $this->repository->add($submission);

        static::assertSame($submission, $this->repository->get($submission->tenantId(), $submission->id()));
        static::assertSame($submission, $this->repository->findByExternalReference($submission->tenantId(), $submission->externalReference()));

        $this->entityManager->clear();
        $reloaded = $this->repository->get($submission->tenantId(), $submission->id());

        static::assertNotSame($submission, $reloaded);
        static::assertSame([], $reloaded->releaseEvents());
        $submission->releaseEvents();
        static::assertEquals($submission, $reloaded);
    }

    public function testTheIdentityOfASubmissionLoadedByAQueryIsComplete(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));
        $this->entityManager->clear();
        $tenant = TenantId::fromString('tenant-1');

        $found = $this->repository->findByExternalReference($tenant, ExternalReference::fromString('host-1'));
        static::assertInstanceOf(Submission::class, $found);
        static::assertSame('s-1', $found->identity()->id()->value());
        $this->entityManager->clear();

        $listed = $this->repository->inState($tenant, null, 10, 0);
        static::assertCount(1, $listed);
        static::assertTrue($listed[0]->identity()->id()->equals($listed[0]->id()));
        static::assertNotSame($found, $listed[0]);
    }

    public function testSavingAnUnmanagedInstanceDropsTheStaleManagedCopy(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));
        $this->entityManager->clear();
        $tenant = TenantId::fromString('tenant-1');
        $id = SubmissionId::fromString('s-1');
        $unmanaged = $this->repository->get($tenant, $id);
        $this->entityManager->detach($unmanaged);
        $managed = $this->repository->get($tenant, $id);

        $unmanaged->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->save($unmanaged);

        static::assertFalse($this->entityManager->contains($managed));
        static::assertSame(SubmissionState::Error, $this->repository->get($tenant, $id)->state());
    }

    public function testTheVersionIsBumpedByEveryWrite(): void
    {
        $failed = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $this->repository->add($failed);
        static::assertSame(1, $this->version());

        $failed->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->save($failed);
        static::assertSame(2, $this->version());

        $failed->reclaim(Instant::fromIso8601('2026-03-01T00:02:00Z'), 5);
        static::assertTrue($this->repository->claim($failed, SubmissionState::Error));
        static::assertSame(3, $this->version());

        SubmissionBuilder::inProcess($failed, 'urssaf-1', '2026-03-01T00:03:00Z');
        $this->repository->save($failed);
        static::assertSame(4, $this->version());
    }

    public function testAClaimDetachesTheClaimedInstanceAndAnyManagedCopy(): void
    {
        $failed = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $failed->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->add($failed);
        $failed->reclaim(Instant::fromIso8601('2026-03-01T00:02:00Z'), 5);

        static::assertTrue($this->repository->claim($failed, SubmissionState::Error));

        static::assertFalse($this->entityManager->contains($failed));
        $claimed = $this->repository->get($failed->tenantId(), $failed->id());
        static::assertNotSame($failed, $claimed);
        static::assertSame(SubmissionState::Uploading, $claimed->state());
        static::assertSame(2, $claimed->attempts());
    }

    public function testALostClaimDetachesTheReclaimedInstanceSoThatTheStoredSubmissionIsReadAgain(): void
    {
        $failed = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $failed->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->add($failed);
        $failed->reclaim(Instant::fromIso8601('2026-03-01T00:02:00Z'), 5);

        static::assertFalse($this->repository->claim($failed, SubmissionState::NotReceived));

        static::assertFalse($this->entityManager->contains($failed));
        $stored = $this->repository->get($failed->tenantId(), $failed->id());
        static::assertSame(SubmissionState::Error, $stored->state());
        static::assertSame(1, $stored->attempts());
        static::assertSame(1, $this->version());
    }

    public function testAClaimOfAnInstanceChangedByAnotherProcessSinceItWasReadLoses(): void
    {
        $failed = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $failed->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->add($failed);
        $this->anotherProcessChangesTheRow();
        $failed->reclaim(Instant::fromIso8601('2026-03-01T00:02:00Z'), 5);

        static::assertFalse($this->repository->claim($failed, SubmissionState::Error));
        static::assertSame(2, $this->version());
    }

    public function testSavingAManagedInstanceChangedByAnotherProcessIsRefused(): void
    {
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $this->repository->add($submission);
        $this->anotherProcessChangesTheRow();
        $submission->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));

        $this->assertConcurrentModification($submission);
    }

    public function testSavingAnUnmanagedInstanceChangedByAnotherProcessIsRefused(): void
    {
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $this->repository->add($submission);
        $this->entityManager->clear();
        $this->anotherProcessChangesTheRow();
        $submission->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));

        $this->assertConcurrentModification($submission);
        static::assertSame(SubmissionState::Uploading, $this->repository->get($submission->tenantId(), $submission->id())->state());
    }

    public function testSavingAnUnmanagedInstanceOfAStoredSubmissionWritesItsChanges(): void
    {
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $this->repository->add($submission);
        $this->entityManager->clear();
        SubmissionBuilder::polled(SubmissionBuilder::inProcess($submission, 'urssaf-1', '2026-03-01T00:01:00Z'), '2026-03-01T00:02:00Z');

        $this->repository->save($submission);
        $this->repository->save($submission);

        static::assertFalse($this->entityManager->contains($submission));
        static::assertSame(3, $this->version());
        $read = $this->repository->get($submission->tenantId(), $submission->id());
        $submission->releaseEvents();
        static::assertEquals($submission, $read);
        static::assertSame(1, $this->rowCount($this->entityManager));
    }

    public function testSavingANewUnmanagedSubmissionStoresIt(): void
    {
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');

        $this->repository->save($submission);
        $this->entityManager->clear();

        static::assertSame('host-1', $this->repository->get($submission->tenantId(), $submission->id())->externalReference()->value());
        static::assertSame(1, $this->version());
    }

    public function testARefusedDuplicateDoesNotCloseTheEntityManager(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        foreach ([['s-2', 'host-1'], ['s-1', 'host-2']] as [$id, $reference]) {
            try {
                $this->repository->add(SubmissionBuilder::declaration($id, 'tenant-1', $reference, '2026-03-01T00:00:00Z'));
                static::fail('Expected a violation.');
            } catch (InvariantViolation) {
                static::assertTrue($this->entityManager->isOpen());
            }
        }
    }

    /**
     * The race of ADR 0011: another process inserts the same external reference (or id) after the checks of add() and
     * before its insert, inside the transaction of the use case. The violation is reported with its code, and the
     * transaction can still read the stored submission and commit (the insert ran in a savepoint).
     */
    public function testADuplicateInsertedConcurrentlyIsReportedAndTheTransactionReadsItAgain(): void
    {
        $cases = [
            'submission.duplicate_external_reference' => ['s-other', 'tenant-1', 'host-1'],
            'submission.duplicate_id' => ['s-1', 'tenant-2', 'host-other'],
        ];
        foreach ($cases as $code => [$id, $tenant, $reference]) {
            $concurrently = new BeforeNextSavepoint();
            $entityManager = TestDatabase::entityManager([$concurrently->middleware()]);
            $repository = new DoctrineSubmissionRepository($entityManager);
            $connection = $entityManager->getConnection();
            $concurrently->arm(static function () use ($connection, $id, $tenant, $reference): void {
                $connection->insert('urssaf_pn_submissions', [
                    'id' => $id,
                    'version' => 1,
                    'tenant_id' => $tenant,
                    'platform_id' => 'platform-1',
                    'external_reference' => $reference,
                    'kind' => 'identification',
                    'file_name' => 'file-1.zip',
                    'fingerprint' => str_repeat('a', 64),
                    'state' => 'uploading',
                    'attempts' => 1,
                    'errors' => '[]',
                    'created_at' => '2026-03-01 00:00:00',
                    'updated_at' => '2026-03-01 00:00:00',
                ]);
            });

            $read = (new DoctrineTransactionManager($entityManager))->transactional(static function () use ($repository, $code, $tenant, $reference): ?Submission {
                try {
                    $repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:01:00Z'));
                    static::fail('Expected a violation.');
                } catch (InvariantViolation $exception) {
                    static::assertSame($code, $exception->violationCode());
                }

                return $repository->findByExternalReference(TenantId::fromString($tenant), ExternalReference::fromString($reference));
            });

            static::assertInstanceOf(Submission::class, $read, $code);
            static::assertSame($id, $read->id()->value());
            static::assertSame('identification', $read->identity()->kind()->value);
            static::assertFalse($connection->isTransactionActive());
            static::assertSame(1, $this->rowCount($entityManager));
            // The failed flush closed the entity manager (ORM 3): the host resets it (docs/doctrine.md).
            static::assertFalse($entityManager->isOpen());
        }
    }

    public function testTheUploadClaimHandlesAConcurrentCreationAsAReentryInItsTransaction(): void
    {
        $concurrently = new BeforeNextSavepoint();
        $entityManager = TestDatabase::entityManager([$concurrently->middleware()]);
        $repository = new DoctrineSubmissionRepository($entityManager);
        $other = new DoctrineSubmissionRepository(TestDatabase::entityManager());
        $concurrent = SubmissionBuilder::identification('s-other', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $other->add($concurrent);
        $connection = $entityManager->getConnection();
        $row = $other->get($concurrent->tenantId(), $concurrent->id());
        $concurrently->arm(static function () use ($connection, $row): void {
            $connection->insert('urssaf_pn_submissions', [
                'id' => $row->id()->value(),
                'version' => 1,
                'tenant_id' => $row->tenantId()->value(),
                'platform_id' => $row->identity()->platformId()->value(),
                'external_reference' => $row->externalReference()->value(),
                'kind' => $row->identity()->kind()->value,
                'file_name' => $row->identity()->fileName()->value(),
                'fingerprint' => $row->identity()->fingerprint()->value(),
                'state' => $row->state()->value,
                'attempts' => 1,
                'errors' => '[]',
                'created_at' => '2026-03-01 00:00:00',
                'updated_at' => '2026-03-01 00:00:00',
            ]);
        });
        $claim = new UploadClaim($repository, new FrozenClock(new DateTimeImmutable('2026-03-01T00:01:00Z')), new WorkflowPolicy());
        $candidate = SubmissionBuilder::identification('s-1', 'tenant-1', 'host-1', '2026-03-01T00:01:00Z')->identity();

        $report = (new DoctrineTransactionManager($entityManager))->transactional(static fn(): Submission|UploadReport => $claim->claim($candidate));

        static::assertInstanceOf(UploadReport::class, $report);
        static::assertSame(UploadOutcome::NotClaimed, $report->outcome);
        static::assertSame('s-other', $report->submissionId->value());
        static::assertSame(1, $this->rowCount($entityManager));
    }

    public function testAUniqueViolationTheRepositoryDoesNotKnowIsRethrown(): void
    {
        $this->entityManager->getConnection()->executeStatement('CREATE UNIQUE INDEX uniq_host_file_name ON urssaf_pn_submissions (file_name)');
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->repository->add(SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-2', '2026-03-01T00:00:00Z'));
    }

    public function testASubmissionOfAnotherTenantIsNeitherFoundByIdNorByReference(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));
        $this->entityManager->clear();

        static::assertNull($this->repository->findByExternalReference(TenantId::fromString('tenant-2'), ExternalReference::fromString('host-1')));
        static::assertSame('s-1', $this->repository->get(TenantId::fromString('tenant-1'), SubmissionId::fromString('s-1'))->id()->value());
    }

    private function anotherProcessChangesTheRow(): void
    {
        $this->entityManager->getConnection()->executeStatement('UPDATE urssaf_pn_submissions SET version = version + 1');
    }

    private function assertConcurrentModification(Submission $submission): void
    {
        try {
            $this->repository->save($submission);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('submission.concurrent_modification', $exception->violationCode());
            static::assertSame(['id' => 's-1'], $exception->params());
            static::assertNull($exception->getPrevious());
        }
    }

    private function rowCount(EntityManagerInterface $entityManager): int
    {
        $count = $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM urssaf_pn_submissions');
        static::assertIsNumeric($count);

        return (int) $count;
    }

    private function version(): int
    {
        $version = $this->entityManager->getConnection()->fetchOne('SELECT version FROM urssaf_pn_submissions');
        static::assertIsNumeric($version);

        return (int) $version;
    }
}
