<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\TenantOverview;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\StateCount;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FixedWorkflowStatistics;

final class GetWorkflowOverviewHandlerTest extends TestCase
{
    public function testEveryTenantWithSubmissionsIsListedInOrder(): void
    {
        $overview = self::handler()(GetWorkflowOverview::allTenants());

        static::assertSame(['tenant-a', 'tenant-b'], array_map(static fn(TenantOverview $tenant): string => $tenant->tenantId, $overview->tenants()));
        static::assertSame(2, $overview->tenants()[0]->states['in_process']);
        static::assertSame(3, $overview->needingAttention());
    }

    public function testOneTenantIsListedEvenWithoutSubmissions(): void
    {
        $known = self::handler()(GetWorkflowOverview::forTenant(TenantId::fromString('tenant-b')));
        $unknown = self::handler()(GetWorkflowOverview::forTenant(TenantId::fromString('tenant-z')));

        static::assertCount(1, $known->tenants());
        static::assertSame(3, $known->needingAttention());
        static::assertCount(1, $unknown->tenants());
        static::assertSame('tenant-z', $unknown->tenants()[0]->tenantId);
        static::assertSame(0, $unknown->needingAttention());
    }

    private static function handler(): GetWorkflowOverviewHandler
    {
        return new GetWorkflowOverviewHandler(new FixedWorkflowStatistics([
            new StateCount(TenantId::fromString('tenant-b'), 'outcome_unknown', 3),
            new StateCount(TenantId::fromString('tenant-a'), 'in_process', 2),
        ]));
    }
}
