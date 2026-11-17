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

use Psr\Clock\ClockInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UtcSystemClock;

/**
 * Container factory of the clock of the package: the application "clock" service (PSR-20) when it exists, the UTC
 * system clock otherwise (symfony/clock is not required).
 */
final class ClockFactory
{
    public static function create(?ClockInterface $applicationClock): Clock
    {
        return $applicationClock instanceof ClockInterface ? new SystemClock($applicationClock) : new UtcSystemClock();
    }
}
