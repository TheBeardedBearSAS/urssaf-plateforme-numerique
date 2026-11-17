<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\DomainEventSamples;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FailingEventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Journal;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\JournalingOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\RecordingFailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\RecordingTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\SequentialIdGenerator;

final class UnitOfWorkTest extends TestCase
{
    public function testPublishesTheRecordedEventsAfterTheCommit(): void
    {
        $harness = new Harness();
        $first = DomainEventSamples::uploaded();
        $second = DomainEventSamples::outcomeUnknown();

        $result = $harness->unitOfWork->run(static function (RecordedEvents $events) use ($first, $second, $harness): string {
            $harness->journal->add('work');
            $events->record($first);
            $events->record($second);

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame([$first, $second], $harness->events->events());
        static::assertSame(
            ['begin', 'work', 'commit', 'publish:SubmissionUploaded', 'publish:SubmissionOutcomeUnknown'],
            $harness->journal->entries(),
        );
    }

    public function testPublishesNothingWhenTheOperationFails(): void
    {
        $harness = new Harness();
        $event = DomainEventSamples::uploaded();
        $failure = new RuntimeException('failure');

        $this->expectExceptionObject($failure);

        try {
            $harness->unitOfWork->run(static function (RecordedEvents $events) use ($event, $failure): never {
                $events->record($event);

                throw $failure;
            });
        } finally {
            static::assertSame([], $harness->events->events());
            static::assertSame(['begin', 'rollback'], $harness->journal->entries());
        }
    }

    public function testRecordedEventsKeepTheOrder(): void
    {
        $first = DomainEventSamples::uploaded();
        $second = DomainEventSamples::outcomeUnknown();
        $events = new RecordedEvents();

        $events->record();
        $events->record(...['first' => $first, 'second' => $second]);
        $events->record($first);

        static::assertSame([$first, $second, $first], $events->all());
    }

    public function testReportsAPublicationFailureWithoutFailingTheCommittedOperation(): void
    {
        $harness = new Harness();
        $publisher = new FailingEventPublisher(new RuntimeException('broker down'));
        $reporter = new RecordingFailureReporter();
        $unitOfWork = new UnitOfWork($harness->transactions, $publisher, $harness->ids, $reporter);
        $event = DomainEventSamples::uploaded();

        $result = $unitOfWork->run(static function (RecordedEvents $events) use ($event): string {
            $events->record($event);

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame([[$publisher->failure, [$event]]], $reporter->reports());
        static::assertSame(['begin', 'commit'], $harness->journal->entries());
    }

    public function testIgnoresAPublicationFailureByDefault(): void
    {
        $harness = new Harness();
        $unitOfWork = new UnitOfWork(
            $harness->transactions,
            new FailingEventPublisher(new RuntimeException('broker down')),
            $harness->ids,
        );

        static::assertSame('done', $unitOfWork->run(static fn(): string => 'done'));
        (new IgnoredFailures())->eventPublicationFailed(new RuntimeException('ignored'), []);
        static::assertSame(1, $harness->transactions->committed());
    }

    public function testTheIntegrationEventsAreAppendedInsideTheTransactionAfterTheOperation(): void
    {
        $journal = new Journal();
        $unitOfWork = new UnitOfWork(
            new RecordingTransactionManager($journal),
            new NullEventPublisher(),
            new SequentialIdGenerator(),
            new IgnoredFailures(),
            new JournalingOutbox($journal),
        );

        $result = $unitOfWork->run(static function (RecordedEvents $events) use ($journal): string {
            $journal->add('work');
            $events->record(DomainEventSamples::uploaded(), DomainEventSamples::uploadFailed(), DomainEventSamples::outcomeUnknown());

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame(
            ['begin', 'work', 'append:urssaf.pn.submission.uploaded,urssaf.pn.submission.outcome_unknown', 'commit'],
            $journal->entries(),
        );
    }

    public function testTheEventIdsComeFromTheGeneratorInRecordingOrder(): void
    {
        $harness = new Harness();

        $harness->unitOfWork->run(static function (RecordedEvents $events): void {
            $events->record(DomainEventSamples::uploaded(), DomainEventSamples::uploadFailed(), DomainEventSamples::outcomeUnknown());
        });

        static::assertSame(
            ['id-1', 'id-2'],
            array_map(static fn(OutboxEntry $entry): string => $entry->event->eventId, $harness->outbox->pending(10)),
        );
    }

    public function testNothingIsAppendedWhenTheOperationFails(): void
    {
        $journal = new Journal();
        $unitOfWork = new UnitOfWork(
            new RecordingTransactionManager($journal),
            new NullEventPublisher(),
            new SequentialIdGenerator(),
            new IgnoredFailures(),
            new JournalingOutbox($journal),
        );

        $this->expectExceptionMessage('failed');

        try {
            $unitOfWork->run(static function (RecordedEvents $events): never {
                $events->record(DomainEventSamples::uploaded());

                throw new RuntimeException('failed');
            });
        } finally {
            static::assertSame(['begin', 'rollback'], $journal->entries());
        }
    }

    public function testTheDefaultOutboxIgnoresTheEvents(): void
    {
        $outbox = new IgnoredOutbox();
        $event = new SubmissionUploaded('e1', '2026-01-15T10:00:00Z', 'tenant-1', 'sub-1', 'ref-1', 'declaration', 'DECLARATION', 'file-1.zip', 'urssaf-1');
        $entry = new OutboxEntry(1, $event, 0);

        $outbox->append($event);
        $outbox->markDispatched($entry, Instant::fromIso8601('2026-01-15T10:00:00Z'));
        $outbox->markFailed($entry, 'ignored');

        static::assertSame([], $outbox->pending(10));
        static::assertSame(0, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-01-15T10:00:00Z')));
    }
}
