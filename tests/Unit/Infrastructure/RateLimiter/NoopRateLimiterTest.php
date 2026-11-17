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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\NoopRateLimiter;

final class NoopRateLimiterTest extends TestCase
{
    public function testNeverThrottles(): void
    {
        $limiter = new NoopRateLimiter();
        $tenant = TenantId::fromString('tenant-a');

        for ($i = 0; $i < 500; ++$i) {
            $limiter->acquire($tenant, Operation::GetNewsFeed);
        }

        $this->expectNotToPerformAssertions();
    }
}
