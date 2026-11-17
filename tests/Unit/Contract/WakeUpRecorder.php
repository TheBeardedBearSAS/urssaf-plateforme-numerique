<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Contract;

/**
 * Records whether an instance was ever unserialized (its __wakeup() ran).
 */
final class WakeUpRecorder
{
    private static bool $wokenUp = false;

    public function __wakeup(): void
    {
        self::$wokenUp = true;
    }

    public static function wokenUp(): bool
    {
        return self::$wokenUp;
    }
}
