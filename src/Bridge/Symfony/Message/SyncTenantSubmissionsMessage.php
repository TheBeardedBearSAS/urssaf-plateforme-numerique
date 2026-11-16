<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message;

/**
 * Asks to poll the URSSAF status of the in-process submissions of one tenant (ADR 0004). Carries the tenant identifier
 * only: it can be retried safely.
 */
final readonly class SyncTenantSubmissionsMessage
{
    public function __construct(public string $tenantId) {}
}
