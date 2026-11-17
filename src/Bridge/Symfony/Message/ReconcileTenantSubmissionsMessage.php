<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message;

/**
 * Asks to reconcile the submissions of one tenant whose upload outcome is unknown (ADR 0003). Carries the tenant
 * identifier only: it can be retried safely.
 */
final readonly class ReconcileTenantSubmissionsMessage
{
    public function __construct(public string $tenantId) {}
}
