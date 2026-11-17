<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;

final class BundleConfigurationOptionsTest extends TestCase
{
    public function testTheLockModesAndTheSmallestTtlAreAccepted(): void
    {
        foreach (['auto', true, false] as $mode) {
            $harness = Harness::boot(['lock' => ['enabled' => $mode, 'ttl' => 1.0]], false, false, true);

            static::assertTrue($harness->hasPrivate(OperationLocks::class));
        }
    }

    public function testTheSmallestRateLimiterQuotaIsAccepted(): void
    {
        $harness = Harness::boot(['rate_limiter' => ['enabled' => true, 'max_calls' => 1, 'window_seconds' => 1]]);

        static::assertInstanceOf(SlidingWindowRateLimiter::class, $harness->privateService(RateLimiter::class, RateLimiter::class));
    }

    public function testTheOptionalTasksOfTheScheduleCanBeLeftOut(): void
    {
        $harness = Harness::boot(['scheduler' => ['enabled' => true, 'sync_frequency' => null, 'reconcile_frequency' => null]]);

        static::assertInstanceOf(UrssafScheduleProvider::class, $harness->privateService(UrssafScheduleProvider::class, UrssafScheduleProvider::class));
    }
}
