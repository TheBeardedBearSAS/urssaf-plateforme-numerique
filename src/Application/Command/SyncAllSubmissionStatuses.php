<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Runs SyncSubmissionStatuses for each given tenant, in order.
 */
final readonly class SyncAllSubmissionStatuses
{
    /**
     * @param list<TenantId> $tenantIds
     */
    private function __construct(public array $tenantIds, public ?int $limitPerTenant) {}

    /**
     * @param ?int $limitPerTenant submissions polled at most per tenant (null: the default page size of the workflow
     *                             policy, capped by its maximum)
     */
    public static function forTenants(?int $limitPerTenant, TenantId ...$tenantIds): self
    {
        return new self(array_values($tenantIds), $limitPerTenant);
    }
}
