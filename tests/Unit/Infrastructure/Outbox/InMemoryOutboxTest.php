<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Outbox;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Outbox;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence\OutboxContract;

final class InMemoryOutboxTest extends TestCase
{
    use OutboxContract;

    public function testTheLastErrorOfAnEntryIsKept(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(self::uploaded('e1'));
        $entry = $outbox->pending(1)[0];

        static::assertNull($outbox->lastError($entry->sequence));

        $outbox->markFailed($entry, 'Broker down.');
        $outbox->markFailed($entry, 'Broker still down.');

        static::assertSame('Broker still down.', $outbox->lastError($entry->sequence));
    }

    public function testASequenceIsNeverReusedAfterAPurge(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(self::uploaded('e1'));
        $first = $outbox->pending(1)[0];
        $outbox->markDispatched($first, Instant::fromIso8601('2026-03-01T10:00:00Z'));
        $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-03-02T10:00:00Z'));

        $outbox->append(self::uploaded('e2'));

        static::assertGreaterThan($first->sequence, $outbox->pending(1)[0]->sequence);
    }

    protected function createOutbox(): Outbox
    {
        return new InMemoryOutbox();
    }
}
