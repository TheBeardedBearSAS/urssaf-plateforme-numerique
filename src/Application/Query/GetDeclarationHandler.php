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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;

final readonly class GetDeclarationHandler
{
    public function __construct(private PlatformGateway $gateway, private PlatformResolver $platforms) {}

    public function __invoke(GetDeclaration $query): DeclarationSubmission
    {
        return $this->gateway->getDeclaration(
            $query->tenantId,
            $this->platforms->forTenant($query->tenantId),
            $query->reference,
        );
    }
}
