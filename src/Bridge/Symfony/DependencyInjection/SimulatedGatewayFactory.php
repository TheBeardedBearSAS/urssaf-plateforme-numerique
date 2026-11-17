<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection;

use LogicException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway;

/**
 * Container factory of the simulated gateway: the runtime guard of the "gateway.simulated" rule for an environment that
 * is a placeholder at build time (the build-time rule only sees a literal "production"), ADR 0012.
 */
final class SimulatedGatewayFactory
{
    /**
     * @param string $environment the resolved "environment" option
     *
     * @throws LogicException when the environment is "production"
     */
    public static function create(Clock $clock, string $environment): SimulatedPlatformGateway
    {
        $target = Environment::from($environment);
        if ($target === Environment::Production) {
            throw new LogicException('"gateway.simulated" is meant for tests and cannot be used with the production environment.');
        }

        return new SimulatedPlatformGateway($clock, $target);
    }
}
