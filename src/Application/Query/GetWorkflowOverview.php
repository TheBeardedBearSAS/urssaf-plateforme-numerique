<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Counts of the submissions by state, for every tenant that has submissions, or for one tenant.
 */
final readonly class GetWorkflowOverview
{
    private function __construct(public ?TenantId $tenantId) {}

    public static function allTenants(): self
    {
        return new self(null);
    }

    public static function forTenant(TenantId $tenantId): self
    {
        return new self($tenantId);
    }
}
