<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Diagnostic;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\MessengerCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\PersistenceCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\SchedulerCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

final class StaticChecksTest extends TestCase
{
    public function testTheSimulatedGatewayIsAWarning(): void
    {
        static::assertEquals(
            [new CheckResult('gateway', '', CheckStatus::Warning, 'Simulated gateway ("gateway.simulated"): nothing is sent to URSSAF.')],
            (new SimulatedGatewayCheck())->run(self::context()),
        );
    }

    public function testTheTokenStoreServiceIsOk(): void
    {
        static::assertEquals(
            [new CheckResult('token store', '', CheckStatus::Ok, 'Service "test.token_store".')],
            (new TokenStoreCheck('test.token_store', 'cache.app'))->run(self::context()),
        );
    }

    public function testTheSharedApplicationCachePoolIsAWarning(): void
    {
        $results = (new TokenStoreCheck(null, 'cache.app'))->run(self::context());

        static::assertCount(1, $results);
        static::assertSame(['token store', '', CheckStatus::Warning], [$results[0]->check, $results[0]->subject, $results[0]->status]);
        static::assertStringStartsWith('Cache pool "cache.app": declare a dedicated pool', $results[0]->detail);
    }

    public function testADedicatedCachePoolIsOk(): void
    {
        static::assertEquals(
            [new CheckResult('token store', '', CheckStatus::Ok, 'Cache pool "cache.system": it must be shared by every process that calls URSSAF.')],
            (new TokenStoreCheck(null, 'cache.system'))->run(self::context()),
        );
    }

    public function testTheInMemoryPersistenceIsAWarning(): void
    {
        static::assertEquals(
            [new CheckResult('persistence', 'in_memory', CheckStatus::Warning, 'In-memory: nothing survives the process, use it for tests only (docs/symfony.md).')],
            (new PersistenceCheck('in_memory'))->run(self::context()),
        );
    }

    public function testTheOtherPersistenceDriversAreOk(): void
    {
        static::assertEquals([new CheckResult('persistence', 'doctrine', CheckStatus::Ok, 'Driver "doctrine".')], (new PersistenceCheck('doctrine'))->run(self::context()));
        static::assertEquals([new CheckResult('persistence', 'custom', CheckStatus::Ok, 'Driver "custom".')], (new PersistenceCheck('custom'))->run(self::context()));
    }

    public function testMessengerIsReported(): void
    {
        static::assertEquals(
            [new CheckResult('messenger', '', CheckStatus::Ok, 'Handlers registered, outbox bus "my.bus": route the messages to a transport of your own (docs/symfony.md).')],
            (new MessengerCheck(true, 'my.bus'))->run(self::context()),
        );
        static::assertEquals(
            [new CheckResult('messenger', '', CheckStatus::Skipped, 'Disabled ("messenger.enabled"): enable it to consume the synchronisation and reconciliation messages from a worker (docs/symfony.md).')],
            (new MessengerCheck(false, 'my.bus'))->run(self::context()),
        );
    }

    public function testTheSchedulerIsReported(): void
    {
        static::assertEquals(
            [new CheckResult('scheduler', '', CheckStatus::Ok, 'Schedule "urssaf_pn": consume it with "messenger:consume scheduler_urssaf_pn".')],
            (new SchedulerCheck(true))->run(self::context()),
        );
        static::assertEquals(
            [new CheckResult('scheduler', '', CheckStatus::Skipped, 'Disabled ("scheduler.enabled"): run the commands from a crontab (docs/symfony.md).')],
            (new SchedulerCheck(false))->run(self::context()),
        );
    }

    private static function context(): CheckContext
    {
        return CheckContext::withoutRemoteCalls([TenantId::fromString('tenant-1')]);
    }
}
