<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence;

use PHPUnit\Framework\TestCase;
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
 * Behaviour every {@see SubmissionRepository} must have, independently of the storage. Identity-related behaviour
 * (copies versus managed instances) is deliberately not part of the contract.
 *
 * Instants are all distinct where an order is asserted: the order of ties is not part of the contract.
 *
 * One method per scenario of the whole port, hence the method count.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 *
 * @mago-expect lint:too-many-methods
 */
trait SubmissionRepositoryContract
{
    protected SubmissionRepository $repository;

    abstract protected function createRepository(): SubmissionRepository;

    protected function setUp(): void
    {
        $this->repository = $this->createRepository();
    }

    /**
     * Ends the unit of work of the storage, as a new request or process would: the next reads load the stored
     * submissions again (the Doctrine adapter clears its entity manager). Nothing to do for a storage that returns
     * copies.
     */
    protected function startNewUnitOfWork(): void {}

    public function testAnAddedSubmissionIsReadBack(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        $read = $this->repository->get(TenantId::fromString('tenant-1'), SubmissionId::fromString('s-1'));

        static::assertSame('host-1', $read->externalReference()->value());
        static::assertSame(SubmissionState::Uploading, $read->state());
        static::assertSame(1, $read->attempts());
        static::assertSame('2026-03-01T00:00:00Z', $read->createdAt()->format());
        static::assertSame(DeclarationType::Declaration, $read->identity()->declarationType());
    }

    public function testAnUnknownSubmissionIsNotFound(): void
    {
        try {
            $this->repository->get(TenantId::fromString('tenant-1'), SubmissionId::fromString('missing'));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('submission.not_found', $exception->violationCode());
            static::assertSame('missing', $exception->identifier());
        }
    }

    public function testASubmissionOfAnotherTenantIsNotFound(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        $this->expectException(RecordNotFound::class);

        $this->repository->get(TenantId::fromString('tenant-2'), SubmissionId::fromString('s-1'));
    }

    public function testTheSameExternalReferenceOfTheTenantIsRefused(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        try {
            $this->repository->add(SubmissionBuilder::identification('s-2', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('submission.duplicate_external_reference', $exception->violationCode());
            static::assertSame(['external_reference' => 'host-1'], $exception->params());
        }

        $this->expectException(RecordNotFound::class);
        $this->repository->get(TenantId::fromString('tenant-1'), SubmissionId::fromString('s-2'));
    }

    public function testTheSameExternalReferenceIsAllowedForAnotherTenant(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));
        $this->repository->add(SubmissionBuilder::declaration('s-2', 'tenant-2', 'host-1', '2026-03-01T00:00:00Z'));
        $this->repository->add(SubmissionBuilder::declaration('s-3', 'tenant-1', 'host-3', '2026-03-01T00:00:00Z'));

        static::assertSame('s-2', $this->repository->get(TenantId::fromString('tenant-2'), SubmissionId::fromString('s-2'))->id()->value());
    }

    public function testAnIdAlreadyStoredIsRefusedWhateverTheTenant(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        foreach (['tenant-1', 'tenant-2'] as $tenant) {
            try {
                $this->repository->add(SubmissionBuilder::identification('s-1', $tenant, 'host-2', '2026-03-01T00:01:00Z'));
                static::fail('Expected a violation.');
            } catch (InvariantViolation $exception) {
                static::assertSame('submission.duplicate_id', $exception->violationCode());
                static::assertSame(['id' => 's-1'], $exception->params());
            }
            static::assertNull($this->repository->findByExternalReference(TenantId::fromString($tenant), ExternalReference::fromString('host-2')));
        }

        $this->startNewUnitOfWork();
        $stored = $this->repository->get(TenantId::fromString('tenant-1'), SubmissionId::fromString('s-1'));
        static::assertSame('host-1', $stored->externalReference()->value());
        static::assertSame('2026-03-01T00:00:00Z', $stored->createdAt()->format());
    }

    public function testTheExternalReferenceIsCheckedBeforeTheId(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        try {
            $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:01:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('submission.duplicate_external_reference', $exception->violationCode());
        }
    }

    public function testASavedChangeIsReadBack(): void
    {
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $this->repository->add($submission);

        SubmissionBuilder::inProcess($submission, 'urssaf-1', '2026-03-01T00:05:00Z');
        $this->repository->save($submission);

        $read = $this->repository->get($submission->tenantId(), $submission->id());
        static::assertSame(SubmissionState::InProcess, $read->state());
        static::assertSame('urssaf-1', $read->urssafReference()?->value());
        static::assertSame('2026-03-01T00:05:00Z', $read->updatedAt()->format());
    }

    public function testDomainEventsAreNotPersistedNorConsumedFromTheCaller(): void
    {
        $submission = SubmissionBuilder::inProcess(
            SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'),
            'urssaf-1',
            '2026-03-01T00:05:00Z',
        );

        $this->repository->add($submission);
        $this->startNewUnitOfWork();

        static::assertSame([], $this->repository->get($submission->tenantId(), $submission->id())->releaseEvents());
        static::assertCount(1, $submission->releaseEvents());
    }

    public function testItFindsByExternalReference(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));
        $this->repository->add(SubmissionBuilder::declaration('s-2', 'tenant-2', 'host-1', '2026-03-01T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-2');

        static::assertSame('s-2', $this->repository->findByExternalReference($tenant, ExternalReference::fromString('host-1'))?->id()->value());
        static::assertNull($this->repository->findByExternalReference($tenant, ExternalReference::fromString('host-9')));
        static::assertNull($this->repository->findByExternalReference(TenantId::fromString('tenant-3'), ExternalReference::fromString('host-1')));
    }

    public function testAClaimPersistsTheReclaimedSubmissionWhenTheStoredStateIsTheExpectedOne(): void
    {
        $failed = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $failed->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->add($failed);
        $reclaimed = $this->repository->get($failed->tenantId(), $failed->id());
        $reclaimed->reclaim(Instant::fromIso8601('2026-03-01T00:02:00Z'), 3);

        static::assertTrue($this->repository->claim($reclaimed, SubmissionState::Error));

        $read = $this->repository->get($failed->tenantId(), $failed->id());
        static::assertSame(SubmissionState::Uploading, $read->state());
        static::assertSame(2, $read->attempts());
        static::assertSame('2026-03-01T00:02:00Z', $read->updatedAt()->format());
    }

    public function testASecondClaimOfTheSameSubmissionLoses(): void
    {
        $failed = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $failed->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->add($failed);
        $this->startNewUnitOfWork();
        $first = $this->repository->get($failed->tenantId(), $failed->id());
        $this->startNewUnitOfWork();
        $second = $this->repository->get($failed->tenantId(), $failed->id());
        $first->reclaim(Instant::fromIso8601('2026-03-01T00:02:00Z'), 5);
        $second->reclaim(Instant::fromIso8601('2026-03-01T00:03:00Z'), 5);

        static::assertTrue($this->repository->claim($first, SubmissionState::Error));
        static::assertFalse($this->repository->claim($second, SubmissionState::Error));

        $read = $this->repository->get($failed->tenantId(), $failed->id());
        static::assertSame('2026-03-01T00:02:00Z', $read->updatedAt()->format());
        static::assertSame(2, $read->attempts());
    }

    public function testAClaimedSubmissionIsSavedWithTheOutcomeOfItsUpload(): void
    {
        $failed = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $failed->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->add($failed);
        $this->startNewUnitOfWork();
        $reclaimed = $this->repository->get($failed->tenantId(), $failed->id());
        $reclaimed->reclaim(Instant::fromIso8601('2026-03-01T00:02:00Z'), 3);
        static::assertTrue($this->repository->claim($reclaimed, SubmissionState::Error));

        SubmissionBuilder::inProcess($reclaimed, 'urssaf-1', '2026-03-01T00:03:00Z');
        $this->repository->save($reclaimed);
        SubmissionBuilder::polled($reclaimed, '2026-03-01T00:04:00Z');
        $this->repository->save($reclaimed);

        $this->startNewUnitOfWork();
        $read = $this->repository->get($failed->tenantId(), $failed->id());
        static::assertSame(SubmissionState::InProcess, $read->state());
        static::assertSame('urssaf-1', $read->urssafReference()?->value());
        static::assertSame(2, $read->attempts());
        static::assertSame('2026-03-01T00:04:00Z', $read->lastPolledAt()?->format());
        static::assertSame('IN_PROCESS', $read->snapshot()?->rawStatus());
    }

    public function testAClaimOfAnUnknownSubmissionFails(): void
    {
        $reclaimed = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');

        static::assertFalse($this->repository->claim($reclaimed, SubmissionState::Error));
        static::assertNull($this->repository->findByExternalReference($reclaimed->tenantId(), $reclaimed->externalReference()));
    }

    public function testItListsTheSubmissionsDueForPollingLeastRecentlyPolledFirst(): void
    {
        $now = Instant::fromIso8601('2026-03-01T12:00:00Z');
        $window = PollingWindow::defaults();
        $this->repository->add(SubmissionBuilder::polled(
            SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'), 'u-1', '2026-03-01T00:01:00Z'),
            '2026-03-01T09:00:00Z',
        ));
        $this->repository->add(SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-2', '2026-03-01T00:00:00Z'), 'u-2', '2026-03-01T00:01:00Z'));
        $this->repository->add(SubmissionBuilder::polled(
            SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-3', 'tenant-1', 'host-3', '2026-03-01T00:00:00Z'), 'u-3', '2026-03-01T00:01:00Z'),
            '2026-03-01T08:00:00Z',
        ));

        static::assertSame(['s-2', 's-3', 's-1'], $this->ids($this->repository->dueForPolling(TenantId::fromString('tenant-1'), $now, $window, 10)));
        static::assertSame(['s-2', 's-3'], $this->ids($this->repository->dueForPolling(TenantId::fromString('tenant-1'), $now, $window, 2)));
    }

    public function testItDoesNotListTheSubmissionsNotDueForPolling(): void
    {
        $now = Instant::fromIso8601('2026-03-01T12:00:00Z');
        $window = PollingWindow::defaults();
        $recentlyPolled = SubmissionBuilder::polled(
            SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'), 'u-1', '2026-03-01T00:01:00Z'),
            '2026-03-01T11:55:00Z',
        );
        $this->repository->add($recentlyPolled);
        $this->repository->add(SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-2', '2026-03-01T00:00:00Z'));
        $this->repository->add(SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-3', 'tenant-2', 'host-3', '2026-03-01T00:00:00Z'), 'u-3', '2026-03-01T00:01:00Z'));
        $this->repository->add(SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-4', 'tenant-1', 'host-4', '2024-01-01T00:00:00Z'), 'u-4', '2024-01-01T00:01:00Z'));

        static::assertSame([], $this->repository->dueForPolling(TenantId::fromString('tenant-1'), $now, $window, 10));
    }

    public function testItPollsAsTheDomainDecidesUpToTheExactBoundaries(): void
    {
        $now = Instant::fromIso8601('2026-03-01T12:00:00Z');
        $declaration = static fn(string $id, string $createdAt = '2026-03-01T00:00:00Z'): Submission => SubmissionBuilder::inProcess(
            SubmissionBuilder::declaration($id, 'tenant-1', 'host-' . $id, $createdAt),
            'u-' . $id,
            $createdAt,
        );
        $candidates = [
            // Processed declarations: polled again after the payment interval until the payment is settled.
            SubmissionBuilder::processed($declaration('s-1'), 'ACCEPTED', 'PROCESSING', '2026-03-01T06:00:00Z'),
            SubmissionBuilder::processed($declaration('s-2'), 'ACCEPTED', 'PROCESSING', '2026-03-01T06:00:01Z'),
            SubmissionBuilder::processed($declaration('s-3'), 'ACCEPTED', 'PROCESSED', '2026-03-01T00:10:00Z'),
            SubmissionBuilder::processed($declaration('s-4'), 'REJECTED', null, '2026-03-01T00:20:00Z'),
            SubmissionBuilder::processed($declaration('s-5'), 'ACCEPTED', null, '2026-03-01T00:30:00Z'),
            SubmissionBuilder::processed($declaration('s-6'), 'REJECTED', 'PROCESSED', '2026-03-01T00:40:00Z'),
            // A processed identification is never polled again.
            SubmissionBuilder::processed(
                SubmissionBuilder::inProcess(SubmissionBuilder::identification('s-7', 'tenant-1', 'host-s-7', '2026-03-01T00:00:00Z'), 'u-s-7', '2026-03-01T00:00:00Z'),
                'ACCEPTED',
                null,
                '2026-03-01T00:10:00Z',
            ),
            // In process: polled again after the status interval, until the maximum age.
            SubmissionBuilder::polled($declaration('s-8'), '2026-03-01T11:50:00Z'),
            SubmissionBuilder::polled($declaration('s-9'), '2026-03-01T11:50:01Z'),
            $declaration('s-10', '2025-03-01T12:00:00Z'),
            $declaration('s-11', '2025-03-01T11:59:59Z'),
        ];
        foreach ($candidates as $candidate) {
            $this->repository->add($candidate);
        }
        $this->startNewUnitOfWork();

        $due = $this->repository->dueForPolling(TenantId::fromString('tenant-1'), $now, PollingWindow::defaults(), 20);

        static::assertSame(['s-10', 's-4', 's-5', 's-6', 's-1', 's-8'], $this->ids($due));
        // The same selection as the domain rule (whatever the order).
        $expected = $this->ids(array_values(array_filter(
            $candidates,
            static fn(Submission $candidate): bool => $candidate->needsPolling($now, PollingWindow::defaults()),
        )));
        $selected = $this->ids($due);
        sort($expected);
        sort($selected);
        static::assertSame($expected, $selected);
    }

    public function testItListsTheUnresolvedSubmissionsOldestUpdateFirst(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T03:00:00Z'));
        $unknown = SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-2', '2026-03-01T01:00:00Z');
        $unknown->markOutcomeUnknown(Instant::fromIso8601('2026-03-01T02:00:00Z'));
        $this->repository->add($unknown);
        $this->repository->add(SubmissionBuilder::declaration('s-3', 'tenant-1', 'host-3', '2026-03-01T00:30:00Z'));
        $this->repository->add(SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-4', 'tenant-1', 'host-4', '2026-03-01T00:00:00Z'), 'u-4', '2026-03-01T00:01:00Z'));
        $this->repository->add(SubmissionBuilder::declaration('s-5', 'tenant-2', 'host-5', '2026-03-01T00:00:00Z'));
        $failed = SubmissionBuilder::declaration('s-6', 'tenant-1', 'host-6', '2026-03-01T00:00:00Z');
        $failed->markFailed(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->add($failed);
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame(['s-3', 's-2', 's-1'], $this->ids($this->repository->unresolved($tenant, 10)));
        static::assertSame(['s-3', 's-2'], $this->ids($this->repository->unresolved($tenant, 2)));
    }

    public function testADeclarationSlotIsOccupiedByAnotherSubmissionOfTheSameTypeInAnActiveState(): void
    {
        $tenant = TenantId::fromString('tenant-1');
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        static::assertTrue($this->repository->occupiesDeclarationSlot($tenant, DeclarationType::Declaration, SubmissionId::fromString('s-2')));
        static::assertFalse($this->repository->occupiesDeclarationSlot($tenant, DeclarationType::Declaration, SubmissionId::fromString('s-1')));
        static::assertFalse($this->repository->occupiesDeclarationSlot($tenant, DeclarationType::Regularisation, SubmissionId::fromString('s-2')));
        static::assertFalse($this->repository->occupiesDeclarationSlot(TenantId::fromString('tenant-2'), DeclarationType::Declaration, SubmissionId::fromString('s-2')));
    }

    public function testEveryActiveStateOccupiesTheSlotAndEverySettledStateFreesIt(): void
    {
        $tenant = TenantId::fromString('tenant-1');
        $other = SubmissionId::fromString('s-other');
        $unknown = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $unknown->markOutcomeUnknown(Instant::fromIso8601('2026-03-01T00:01:00Z'));
        $this->repository->add($unknown);
        static::assertTrue($this->repository->occupiesDeclarationSlot($tenant, DeclarationType::Declaration, $other));

        $unknown->markNotReceived(Instant::fromIso8601('2026-03-01T00:02:00Z'));
        $this->repository->save($unknown);
        static::assertFalse($this->repository->occupiesDeclarationSlot($tenant, DeclarationType::Declaration, $other));

        $inProcess = SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-2', '2026-03-01T00:00:00Z'), 'u-2', '2026-03-01T00:01:00Z');
        $this->repository->add($inProcess);
        static::assertTrue($this->repository->occupiesDeclarationSlot($tenant, DeclarationType::Declaration, $other));
    }

    public function testAnIdentificationNeverOccupiesADeclarationSlot(): void
    {
        $this->repository->add(SubmissionBuilder::identification('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        static::assertFalse($this->repository->occupiesDeclarationSlot(TenantId::fromString('tenant-1'), DeclarationType::Declaration, SubmissionId::fromString('s-2')));
    }

    public function testAnUrssafReferenceIsKnownForTheTenantAndTheKindOnly(): void
    {
        $this->repository->add(SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'), 'u-1', '2026-03-01T00:01:00Z'));
        $this->repository->add(SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-2', '2026-03-01T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');
        $reference = UrssafReference::fromString('u-1');

        static::assertTrue($this->repository->isUrssafReferenceKnown($tenant, SubmissionKind::Declaration, $reference));
        static::assertFalse($this->repository->isUrssafReferenceKnown($tenant, SubmissionKind::Identification, $reference));
        static::assertFalse($this->repository->isUrssafReferenceKnown(TenantId::fromString('tenant-2'), SubmissionKind::Declaration, $reference));
        static::assertFalse($this->repository->isUrssafReferenceKnown($tenant, SubmissionKind::Declaration, UrssafReference::fromString('u-2')));
    }

    public function testItListsTheSubmissionsOfAStateMostRecentFirstWithPagination(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T01:00:00Z'));
        $this->repository->add(SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-2', '2026-03-01T03:00:00Z'));
        $this->repository->add(SubmissionBuilder::declaration('s-3', 'tenant-1', 'host-3', '2026-03-01T02:00:00Z'));
        $this->repository->add(SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-4', 'tenant-1', 'host-4', '2026-03-01T04:00:00Z'), 'u-4', '2026-03-01T04:01:00Z'));
        $this->repository->add(SubmissionBuilder::declaration('s-5', 'tenant-2', 'host-5', '2026-03-01T05:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame(['s-2', 's-3', 's-1'], $this->ids($this->repository->inState($tenant, SubmissionState::Uploading, 10, 0)));
        static::assertSame(['s-3'], $this->ids($this->repository->inState($tenant, SubmissionState::Uploading, 1, 1)));
        static::assertSame(['s-1'], $this->ids($this->repository->inState($tenant, SubmissionState::Uploading, 5, 2)));
        static::assertSame(['s-4'], $this->ids($this->repository->inState($tenant, SubmissionState::InProcess, 10, 0)));
        static::assertSame([], $this->repository->inState($tenant, SubmissionState::Accepted, 10, 0));
    }

    public function testItListsEveryStateWhenNoneIsGiven(): void
    {
        $this->repository->add(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T01:00:00Z'));
        $this->repository->add(SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-2', '2026-03-01T02:00:00Z'), 'u-2', '2026-03-01T02:01:00Z'));
        $this->repository->add(SubmissionBuilder::declaration('s-3', 'tenant-2', 'host-3', '2026-03-01T03:00:00Z'));

        static::assertSame(['s-2', 's-1'], $this->ids($this->repository->inState(TenantId::fromString('tenant-1'), null, 10, 0)));
    }

    /**
     * @param list<Submission> $submissions
     *
     * @return list<string>
     */
    private function ids(array $submissions): array
    {
        return array_map(static fn(Submission $submission): string => $submission->id()->value(), $submissions);
    }
}
