<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\RateLimiter;

use DateTimeImmutable;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\AdjustableClock;

final class RateLimiterProbe
{
    private function __construct() {}

    public static function clock(): AdjustableClock
    {
        return new AdjustableClock(new DateTimeImmutable('2026-01-01T10:00:00+00:00'));
    }

    public static function fill(RateLimiter $limiter, TenantId $tenant, int $calls): void
    {
        for ($i = 0; $i < $calls; ++$i) {
            $limiter->acquire($tenant, Operation::GetNewsFeed);
        }
    }

    /**
     * Returns the retry delay when the call is rejected, null when it is allowed.
     */
    public static function retryAfterOrNull(RateLimiter $limiter, TenantId $tenant, Operation $operation): ?int
    {
        try {
            $limiter->acquire($tenant, $operation);
        } catch (RateLimitExceeded $exception) {
            return $exception->retryAfterSeconds();
        }

        return null;
    }
}
