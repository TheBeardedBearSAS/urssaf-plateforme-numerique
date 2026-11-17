<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class PurgeOutboxCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:outbox:purge';

    public function testDeletesTheEntriesDispatchedForLongerThanTheRetention(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher', 'retention' => 'P10D']]);
        Scenario::uploadedIdentification($harness);
        $harness->command('urssaf:pn:outbox:relay')->execute([]);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString('0 dispatched outbox entries deleted.', $tester->getDisplay());

        $harness->clock->sleep(11 * 86_400);

        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString('1 dispatched outbox entries deleted.', $tester->getDisplay());
        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString('0 dispatched outbox entries deleted.', $tester->getDisplay());
    }

    public function testKeepsThePendingEntries(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher', 'retention' => 'P10D']]);
        Scenario::uploadedIdentification($harness);
        $harness->clock->sleep(11 * 86_400);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertStringContainsString('0 dispatched outbox entries deleted.', $tester->getDisplay());
    }
}
