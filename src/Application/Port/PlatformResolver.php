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

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Tells which URSSAF platform (the identifier of the platform declared to URSSAF) a tenant submits for (ADR 0007).
 */
interface PlatformResolver
{
    /**
     * @throws RecordNotFound "platform.not_found" when the tenant has no platform
     */
    public function forTenant(TenantId $tenantId): PlatformId;
}
