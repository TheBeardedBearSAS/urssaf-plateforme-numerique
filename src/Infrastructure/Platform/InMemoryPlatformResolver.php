<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Platform;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Platform resolver backed by a map of tenant identifier to platform (configuration or tests), ADR 0007.
 */
final readonly class InMemoryPlatformResolver implements PlatformResolver
{
    /**
     * @param array<string, PlatformId> $platformByTenant keyed by tenant identifier
     */
    public function __construct(private array $platformByTenant) {}

    /**
     * @throws RecordNotFound "platform.not_found"
     */
    public function forTenant(TenantId $tenantId): PlatformId
    {
        return $this->platformByTenant[$tenantId->value()]
            ?? throw RecordNotFound::withIdentifier('platform', $tenantId->value());
    }
}
