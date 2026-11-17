<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Id;

use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id\RandomBytesSource;

/**
 * Random bytes fake returning the given bytes and recording the requested lengths.
 */
final class FixedRandomBytes implements RandomBytesSource
{
    /** @var list<int> */
    private array $requested = [];

    public function __construct(private readonly string $bytes) {}

    public function bytes(int $length): string
    {
        $this->requested[] = $length;

        return $this->bytes;
    }

    /**
     * @return list<int>
     */
    public function requested(): array
    {
        return $this->requested;
    }
}
