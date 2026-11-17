<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\TenantOverview;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\WorkflowOverview;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\StateCount;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

final class WorkflowOverviewTest extends TestCase
{
    private const NO_SUBMISSION = [
        'uploading' => 0,
        'in_process' => 0,
        'accepted' => 0,
        'rejected' => 0,
        'upload_rejected' => 0,
        'error' => 0,
        'invalid_credentials' => 0,
        'outcome_unknown' => 0,
        'not_received' => 0,
    ];

    public function testEveryTenantWithSubmissionsIsListedInOrderWithEveryState(): void
    {
        $overview = WorkflowOverview::allTenants(self::counts());

        $tenants = $overview->tenants();
        static::assertSame(['tenant-a', 'tenant-b', 'tenant-c'], array_map(static fn(TenantOverview $tenant): string => $tenant->tenantId, $tenants));
        static::assertSame(array_replace(self::NO_SUBMISSION, ['in_process' => 4, 'invalid_credentials' => 1]), $tenants[0]->states);
        static::assertSame(array_keys(self::NO_SUBMISSION), array_keys($tenants[0]->states));
        static::assertSame(array_replace(self::NO_SUBMISSION, ['uploading' => 3]), $tenants[1]->states);
        static::assertSame(array_replace(self::NO_SUBMISSION, ['outcome_unknown' => 2]), $tenants[2]->states);
    }

    public function testNoTenantWithoutSubmissions(): void
    {
        static::assertSame([], WorkflowOverview::allTenants([])->tenants());
        static::assertSame(0, WorkflowOverview::allTenants([])->needingAttention());
    }

    public function testOneTenantIsListedEvenWithoutSubmissions(): void
    {
        $known = WorkflowOverview::forTenant(TenantId::fromString('tenant-b'), self::counts())->tenants();
        $unknown = WorkflowOverview::forTenant(TenantId::fromString('tenant-z'), self::counts())->tenants();

        static::assertCount(1, $known);
        static::assertSame('tenant-b', $known[0]->tenantId);
        static::assertSame(array_replace(self::NO_SUBMISSION, ['uploading' => 3]), $known[0]->states);
        static::assertCount(1, $unknown);
        static::assertSame('tenant-z', $unknown[0]->tenantId);
        static::assertSame(self::NO_SUBMISSION, $unknown[0]->states);
    }

    public function testTheSubmissionsWaitingForAManualActionAreSummed(): void
    {
        $counts = [
            self::stateCount('t', 'error', 1),
            self::stateCount('t', 'invalid_credentials', 2),
            self::stateCount('t', 'outcome_unknown', 4),
            self::stateCount('t', 'rejected', 50),
            self::stateCount('t', 'upload_rejected', 50),
            self::stateCount('t', 'not_received', 50),
            self::stateCount('t', 'in_process', 50),
            self::stateCount('u', 'error', 8),
        ];

        $overview = WorkflowOverview::allTenants($counts);

        static::assertSame(7, $overview->tenants()[0]->needingAttention());
        static::assertSame(8, $overview->tenants()[1]->needingAttention());
        static::assertSame(15, $overview->needingAttention());
        static::assertSame(
            ['tenant_id' => 't', 'states' => $overview->tenants()[0]->states, 'needing_attention' => 7],
            $overview->tenants()[0]->toArray(),
        );
    }

    public function testAnUnknownStateIsIgnored(): void
    {
        $tenant = TenantOverview::of(TenantId::fromString('t'), [self::stateCount('t', 'archived', 3), self::stateCount('t', 'error', 1)]);

        static::assertSame(array_replace(self::NO_SUBMISSION, ['error' => 1]), $tenant->states);
    }

    public function testTheCountsOfAnotherTenantAreIgnored(): void
    {
        $tenant = TenantOverview::of(TenantId::fromString('t'), [self::stateCount('other', 'error', 9)]);

        static::assertSame(self::NO_SUBMISSION, $tenant->states);
        static::assertSame(0, $tenant->needingAttention());
    }

    /**
     * @return list<StateCount>
     */
    private static function counts(): array
    {
        return [
            self::stateCount('tenant-c', 'outcome_unknown', 2),
            self::stateCount('tenant-a', 'in_process', 4),
            self::stateCount('tenant-b', 'uploading', 3),
            self::stateCount('tenant-a', 'invalid_credentials', 1),
        ];
    }

    private static function stateCount(string $tenant, string $state, int $count): StateCount
    {
        return new StateCount(TenantId::fromString($tenant), $state, $count);
    }
}
