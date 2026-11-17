<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Outbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence\OutboxContract;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\DomainEventSamples;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\SequentialIdGenerator;

final class DoctrineOutboxTest extends TestCase
{
    use OutboxContract;

    protected function createOutbox(): Outbox
    {
        return new DoctrineOutbox(TestDatabase::entityManager());
    }

    public function testTheEventsAreWrittenWithTheStateChangeOrNotAtAll(): void
    {
        $entityManager = TestDatabase::entityManager();
        $outbox = new DoctrineOutbox($entityManager);
        $unitOfWork = self::unitOfWork($entityManager, $outbox);

        $unitOfWork->run(static fn(RecordedEvents $events) => $events->record(DomainEventSamples::uploaded()));
        try {
            $unitOfWork->run(static function (RecordedEvents $events): never {
                $events->record(DomainEventSamples::notReceived());

                throw new RuntimeException('Rolled back.');
            });
        } catch (RuntimeException $failure) {
            static::assertNotSame('', $failure->getMessage());
        }

        $pending = $outbox->pending(10);
        static::assertCount(1, $pending);
        $event = $pending[0]->event;
        static::assertSame('urssaf.pn.submission.uploaded', $event->type());
        $row = $entityManager->getConnection()->fetchAssociative('SELECT * FROM urssaf_pn_outbox');
        static::assertIsArray($row);
        static::assertEquals(
            [$event->eventId, $event->type(), 1, $event->tenantId, $event->aggregateId, $event->occurredAt, null, 0, null],
            [$row['event_id'], $row['type'], $row['version'], $row['tenant_id'], $row['aggregate_id'], $row['occurred_at'], $row['dispatched_at'], $row['attempts'], $row['last_error']],
        );
        static::assertIsString($row['payload']);
        static::assertSame($event->toArray(), json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testTheLastFailureIsKept(): void
    {
        $entityManager = TestDatabase::entityManager();
        $outbox = new DoctrineOutbox($entityManager);
        self::unitOfWork($entityManager, $outbox)->run(static fn(RecordedEvents $events) => $events->record(DomainEventSamples::uploaded()));

        $outbox->markFailed($outbox->pending(1)[0], 'Broker down.');

        static::assertSame('Broker down.', $entityManager->getConnection()->fetchOne('SELECT last_error FROM urssaf_pn_outbox'));
    }

    private static function unitOfWork(EntityManagerInterface $entityManager, Outbox $outbox): UnitOfWork
    {
        return new UnitOfWork(new DoctrineTransactionManager($entityManager), new NullEventPublisher(), new SequentialIdGenerator(), new IgnoredFailures(), $outbox);
    }
}
