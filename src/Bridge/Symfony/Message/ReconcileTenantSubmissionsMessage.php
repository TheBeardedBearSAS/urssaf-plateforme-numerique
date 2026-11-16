<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message;

/**
 * Asks to reconcile the submissions of one tenant whose upload outcome is unknown (ADR 0003). Carries the tenant
 * identifier only: it can be retried safely.
 */
final readonly class ReconcileTenantSubmissionsMessage
{
    public function __construct(public string $tenantId) {}
}
