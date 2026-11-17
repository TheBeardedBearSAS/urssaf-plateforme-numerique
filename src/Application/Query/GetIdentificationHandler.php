<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;

final readonly class GetIdentificationHandler
{
    public function __construct(private PlatformGateway $gateway, private PlatformResolver $platforms) {}

    public function __invoke(GetIdentification $query): IdentificationSubmission
    {
        return $this->gateway->getIdentification(
            $query->tenantId,
            $this->platforms->forTenant($query->tenantId),
            $query->reference,
        );
    }
}
