<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Port;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionNotReceived;

final class OutboxEntryTest extends TestCase
{
    public function testProperties(): void
    {
        $event = new SubmissionNotReceived(
            'event-1',
            '2026-01-01T10:00:00Z',
            'tenant-1',
            'submission-1',
            'ext-1',
            'identification',
            null,
            'file.csv',
            null,
        );

        $entry = new OutboxEntry(42, $event, 3);

        static::assertSame(42, $entry->sequence);
        static::assertSame($event, $entry->event);
        static::assertSame(3, $entry->attempts);
    }
}
