<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;

final class HttpStacks
{
    private function __construct() {}

    public static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function sandbox(): HttpStack
    {
        return new HttpStack(ApiConfiguration::forEnvironment(Environment::Sandbox), 0);
    }

    public static function configured(ApiConfiguration $configuration): HttpStack
    {
        return new HttpStack($configuration, 0);
    }

    /**
     * Sandbox stack whose clock advances by the given number of microseconds at every now() call.
     */
    public static function stepping(int $stepMicroseconds): HttpStack
    {
        return new HttpStack(ApiConfiguration::forEnvironment(Environment::Sandbox), $stepMicroseconds);
    }
}
