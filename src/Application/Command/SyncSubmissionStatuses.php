<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Polls the submissions of a tenant whose URSSAF status may still change (ADR 0004).
 */
final readonly class SyncSubmissionStatuses
{
    private function __construct(public TenantId $tenantId, public ?int $limit) {}

    /**
     * @param ?int $limit submissions polled at most (null: the default page size of the workflow policy, capped by its
     *                    maximum)
     */
    public static function forTenant(TenantId $tenantId, ?int $limit): self
    {
        return new self($tenantId, $limit);
    }
}
