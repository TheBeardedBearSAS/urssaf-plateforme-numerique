<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Outbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionNotReceived;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

/**
 * Behaviour shared by every {@see Outbox} adapter.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 */
trait OutboxContract
{
    abstract protected function createOutbox(): Outbox;

    public function testTheAppendedEventsArePendingInRecordingOrder(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::uploaded('e1'), self::notReceived('e2'));
        $outbox->append(self::uploaded('e3'));

        $pending = $outbox->pending(10);

        static::assertSame(['e1', 'e2', 'e3'], self::ids($pending));
        static::assertEquals(self::notReceived('e2'), $pending[1]->event);
        static::assertSame([0, 0, 0], array_map(static fn(OutboxEntry $entry): int => $entry->attempts, $pending));
        static::assertLessThan($pending[1]->sequence, $pending[0]->sequence);
        static::assertLessThan($pending[2]->sequence, $pending[1]->sequence);
        static::assertSame(['e1', 'e2'], self::ids($outbox->pending(2)));
    }

    public function testADispatchedEntryIsNoLongerPending(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::uploaded('e1'), self::uploaded('e2'));

        $outbox->markDispatched($outbox->pending(1)[0], Instant::fromIso8601('2026-03-01T10:00:00Z'));

        static::assertSame(['e2'], self::ids($outbox->pending(10)));
    }

    public function testAFailedEntryStaysPendingWithOneMoreAttempt(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::uploaded('e1'));

        $outbox->markFailed($outbox->pending(1)[0], 'Broker down.');
        $outbox->markFailed($outbox->pending(1)[0], 'Broker still down.');

        $pending = $outbox->pending(10);
        static::assertSame(['e1'], self::ids($pending));
        static::assertSame(2, $pending[0]->attempts);
    }

    public function testThePurgeDeletesTheEntriesDispatchedBeforeTheInstantOnly(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::uploaded('e1'), self::uploaded('e2'), self::uploaded('e3'));
        [$first, $second] = $outbox->pending(2);
        $outbox->markDispatched($first, Instant::fromIso8601('2026-03-01T10:00:00Z'));
        $outbox->markDispatched($second, Instant::fromIso8601('2026-03-02T10:00:00Z'));

        static::assertSame(0, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-03-01T10:00:00Z')));
        static::assertSame(1, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-03-02T10:00:00Z')));
        static::assertSame(1, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-03-03T10:00:00Z')));
        static::assertSame(['e3'], self::ids($outbox->pending(10)));
    }

    private static function uploaded(string $eventId): SubmissionUploaded
    {
        return new SubmissionUploaded(
            $eventId,
            '2026-03-01T10:00:00Z',
            'tenant-1',
            'submission-1',
            'host-ref-1',
            'declaration',
            'DECLARATION',
            'file-1.zip',
            'urssaf-ref-1',
        );
    }

    private static function notReceived(string $eventId): SubmissionNotReceived
    {
        return new SubmissionNotReceived(
            $eventId,
            '2026-03-01T10:00:00Z',
            'tenant-1',
            'submission-2',
            'host-ref-2',
            'identification',
            null,
            'file-2.zip',
            null,
        );
    }

    /**
     * @param list<OutboxEntry> $entries
     *
     * @return list<string>
     */
    private static function ids(array $entries): array
    {
        return array_map(static fn(OutboxEntry $entry): string => $entry->event->eventId, $entries);
    }
}
