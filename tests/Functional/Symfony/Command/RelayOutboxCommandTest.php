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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\ThrowingListener;

final class RelayOutboxCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:outbox:relay';

    public function testRelaysThePendingEvents(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]);
        $relayed = [];
        $harness->privateService('event_dispatcher', EventDispatcherInterface::class)->addListener(
            SubmissionUploaded::class,
            static function (SubmissionUploaded $event) use (&$relayed): void {
                $relayed[] = $event;
            },
        );
        Scenario::uploadedIdentification($harness);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertSame([['Dispatched', 'Failed', 'Deferred'], ['1', '0', '0']], Output::rows($tester->getDisplay()));
        static::assertCount(1, $relayed);
        static::assertSame(0, $tester->execute([]));
        static::assertSame([['Dispatched', 'Failed', 'Deferred'], ['0', '0', '0']], Output::rows($tester->getDisplay()));
    }

    public function testTheLimitCapsTheEventsRead(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]);
        Scenario::uploadedIdentification($harness, 'tenant-1', 'ref-1');
        Scenario::uploadedIdentification($harness, 'tenant-1', 'ref-2');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--limit' => '1']));

        static::assertSame([['Dispatched', 'Failed', 'Deferred'], ['1', '0', '0']], Output::rows($tester->getDisplay()));
    }

    public function testAFailedDeliveryFailsTheCommand(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]);
        $harness->privateService('event_dispatcher', EventDispatcherInterface::class)->addListener(SubmissionUploaded::class, new ThrowingListener());
        Scenario::uploadedIdentification($harness);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));

        static::assertSame([['Dispatched', 'Failed', 'Deferred'], ['0', '1', '0']], Output::rows($tester->getDisplay()));
    }

    public function testRefusesAnInvalidLimit(): void
    {
        $tester = Harness::boot(['outbox' => ['enabled' => true]])->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['--limit' => '0']));
        static::assertStringContainsString('The --limit option must be a positive integer.', $tester->getDisplay());
    }

    public function testIsSkippedWhenAnotherRelayIsRunning(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']], false, false, true);
        Scenario::uploadedIdentification($harness);
        $held = HeldLocks::hold($harness, 'urssaf_pn.outbox.relay.*');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertStringContainsString('Already running (lock "urssaf_pn.outbox.relay.*" is held): skipped.', $tester->getDisplay());
        static::assertSame([], Output::rows($tester->getDisplay()));
        static::assertTrue($held->isAcquired());
    }
}
