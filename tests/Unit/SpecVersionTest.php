<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\SpecVersion;

final class SpecVersionTest extends TestCase
{
    public function testItExposesTheImplementedSpecificationVersion(): void
    {
        static::assertMatchesRegularExpression('/^1\.0\.0$/', SpecVersion::CURRENT);
    }
}
