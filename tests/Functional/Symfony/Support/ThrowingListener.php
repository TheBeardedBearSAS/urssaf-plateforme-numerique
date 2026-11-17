<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support;

use LogicException;

/**
 * An event listener that always fails.
 */
final class ThrowingListener
{
    public function __invoke(): never
    {
        throw new LogicException('listener exploded');
    }
}
