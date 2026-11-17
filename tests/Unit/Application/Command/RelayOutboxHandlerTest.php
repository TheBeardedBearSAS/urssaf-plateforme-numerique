<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FixedClock;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;

final class RelayOutboxHandlerTest extends TestCase
{
    private const NOW = '2026-01-15T10:00:00Z';

    public function testThePendingEventsAreDeliveredInOrderThenMarkedAsDispatched(): void
    {
        $outbox = self::outbox(['e1', 'a'], ['e2', 'b'], ['e3', 'a']);
        $sink = new RecordingIntegrationEventSink();

        $report = self::handler($outbox, $sink)(RelayOutbox::of(null));

        static::assertSame(['e1', 'e2', 'e3'], $sink->deliveredIds());
        static::assertSame([3, 0, 0], [$report->dispatched, $report->failed, $report->deferred]);
        static::assertSame([], $outbox->pending(10));
        static::assertSame(3, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-01-15T10:00:01Z')));
    }

    public function testAFailedEventStaysPendingAndHoldsBackTheNextEventsOfItsRecordOnly(): void
    {
        $outbox = self::outbox(['e1', 'a'], ['e2', 'b'], ['e3', 'a'], ['e4', 'c']);
        $sink = new RecordingIntegrationEventSink(['a']);

        $report = self::handler($outbox, $sink)(RelayOutbox::of(null));

        static::assertSame(['e2', 'e4'], $sink->deliveredIds());
        static::assertSame([2, 1, 1], [$report->dispatched, $report->failed, $report->deferred]);
        $pending = $outbox->pending(10);
        static::assertSame([[1, 1], [3, 0]], array_map(static fn(OutboxEntry $entry): array => [$entry->sequence, $entry->attempts], $pending));
        static::assertSame('Broker down for a.', $outbox->lastError(1));
        static::assertNull($outbox->lastError(3));
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $outbox = self::outbox(['e1', 'a'], ['e2', 'b'], ['e3', 'c']);
        $sink = new RecordingIntegrationEventSink();
        $policy = new WorkflowPolicy(
            new DateInterval('PT10M'),
            new DateInterval('PT6H'),
            new DateInterval('P1Y'),
            5,
            new DateInterval('PT1H'),
            new DateInterval('P1D'),
            1,
            true,
            new DateInterval('P30D'),
            2,
            2,
        );
        $handler = new RelayOutboxHandler($outbox, $sink, new FixedClock(self::NOW), $policy);

        $handler(RelayOutbox::of(null));
        static::assertSame(['e1', 'e2'], $sink->deliveredIds());
        $handler(RelayOutbox::of(1));
        static::assertSame(['e1', 'e2', 'e3'], $sink->deliveredIds());
    }

    public function testThePurgeKeepsTheEntriesDispatchedWithinTheRetention(): void
    {
        $outbox = self::outbox(['e1', 'a']);
        self::handler($outbox, new RecordingIntegrationEventSink())(RelayOutbox::of(null));
        $purge = new PurgeOutboxHandler($outbox, new FixedClock('2026-02-14T10:00:00Z'), new WorkflowPolicy());

        static::assertSame(0, $purge(PurgeOutbox::withPolicyRetention()));
        static::assertSame(0, $purge(PurgeOutbox::of(new DateInterval('P30D'))));
        static::assertSame(1, $purge(PurgeOutbox::of(new DateInterval('P29DT23H59M59S'))));
    }

    public function testThePolicyRetentionApplies(): void
    {
        $outbox = self::outbox(['e1', 'a']);
        self::handler($outbox, new RecordingIntegrationEventSink())(RelayOutbox::of(null));
        $purge = new PurgeOutboxHandler($outbox, new FixedClock('2026-02-14T10:00:01Z'), new WorkflowPolicy());

        static::assertSame(1, $purge(PurgeOutbox::withPolicyRetention()));
    }

    /**
     * @param array{string, string} ...$events event id and aggregate id of each event
     */
    private static function outbox(array ...$events): InMemoryOutbox
    {
        $outbox = new InMemoryOutbox();
        foreach ($events as [$eventId, $aggregateId]) {
            $outbox->append(new SubmissionUploaded($eventId, self::NOW, 'tenant-1', $aggregateId, 'ref-' . $aggregateId, 'identification', null, 'file.zip', 'urssaf-' . $aggregateId));
        }

        return $outbox;
    }

    private static function handler(InMemoryOutbox $outbox, RecordingIntegrationEventSink $sink): RelayOutboxHandler
    {
        return new RelayOutboxHandler($outbox, $sink, new FixedClock(self::NOW), new WorkflowPolicy());
    }
}
