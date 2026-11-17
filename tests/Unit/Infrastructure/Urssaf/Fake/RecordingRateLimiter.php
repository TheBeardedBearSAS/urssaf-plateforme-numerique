<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

final class RecordingRateLimiter implements RateLimiter
{
    /** @var list<string> */
    private array $acquired = [];

    public function acquire(TenantId $tenantId, Operation $operation): void
    {
        $this->acquired[] = $tenantId->value() . ':' . $operation->value;
    }

    /**
     * @return list<string>
     */
    public function acquired(): array
    {
        return $this->acquired;
    }
}
