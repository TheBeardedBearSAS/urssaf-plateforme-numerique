<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id;

use Random\RandomException;

/**
 * Random bytes from the operating system through random_bytes().
 */
final class SecureRandomBytes implements RandomBytesSource
{
    /**
     * @param int<1, max> $length
     *
     * @throws RandomException when no secure source of randomness is available
     */
    public function bytes(int $length): string
    {
        return random_bytes($length);
    }
}
