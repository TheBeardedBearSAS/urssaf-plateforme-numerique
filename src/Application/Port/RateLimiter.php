<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Client-side throttling, per tenant (each tenant has its own credentials). No quota is published by URSSAF, so the
 * default implementation does not throttle (ADR 0002).
 */
interface RateLimiter
{
    /**
     * Reserves one call slot, blocking until one is available or throwing when the implementation is non-blocking.
     *
     * @throws RateLimitExceeded
     */
    public function acquire(TenantId $tenantId, Operation $operation): void;
}
