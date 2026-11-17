<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\StateCount;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * The submissions by tenant and state, for dashboards and alerting.
 */
final readonly class WorkflowOverview
{
    /**
     * @param list<TenantOverview> $tenants
     */
    private function __construct(private array $tenants) {}

    /**
     * Every tenant that has submissions, sorted by id.
     *
     * @param list<StateCount> $counts
     */
    public static function allTenants(array $counts): self
    {
        $tenants = [];
        foreach ($counts as $count) {
            $tenants[$count->tenantId->value()] = $count->tenantId;
        }
        ksort($tenants, SORT_STRING);

        return new self(array_values(array_map(
            static fn(TenantId $tenantId): TenantOverview => TenantOverview::of($tenantId, $counts),
            $tenants,
        )));
    }

    /**
     * Exactly the given tenant, even without submissions.
     *
     * @param list<StateCount> $counts
     */
    public static function forTenant(TenantId $tenantId, array $counts): self
    {
        return new self([TenantOverview::of($tenantId, $counts)]);
    }

    /**
     * @return list<TenantOverview>
     */
    public function tenants(): array
    {
        return $this->tenants;
    }

    /**
     * Submissions that wait for a manual action, all tenants together.
     */
    public function needingAttention(): int
    {
        return array_sum(array_map(static fn(TenantOverview $tenant): int => $tenant->needingAttention(), $this->tenants));
    }
}
