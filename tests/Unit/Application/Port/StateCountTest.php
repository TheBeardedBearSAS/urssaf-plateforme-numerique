<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Port;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\StateCount;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

final class StateCountTest extends TestCase
{
    public function testProperties(): void
    {
        $tenantId = TenantId::fromString('tenant-1');

        $count = new StateCount($tenantId, 'in_process', 7);

        static::assertSame($tenantId, $count->tenantId);
        static::assertSame('in_process', $count->state);
        static::assertSame(7, $count->count);
    }
}
