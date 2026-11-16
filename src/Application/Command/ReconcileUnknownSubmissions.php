<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Looks for the uploads of a tenant whose outcome is unknown (or stale) in the URSSAF searches (ADR 0003).
 */
final readonly class ReconcileUnknownSubmissions
{
    private function __construct(public TenantId $tenantId, public ?int $limit) {}

    /**
     * @param ?int $limit submissions reconciled at most (null: the default page size of the workflow policy, capped by
     *                    its maximum)
     */
    public static function forTenant(TenantId $tenantId, ?int $limit): self
    {
        return new self($tenantId, $limit);
    }
}
