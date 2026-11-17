<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Identification;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationDetails;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;

final class IdentificationDetailsTest extends TestCase
{
    public function testGetters(): void
    {
        $declared = DecimalNumber::fromInt(10);
        $identified = DecimalNumber::fromInt(9);
        $errors = DecimalNumber::fromInt(1);

        $details = IdentificationDetails::of($declared, $identified, $errors);

        static::assertSame($declared, $details->numberOfDeclaredUser());
        static::assertSame($identified, $details->numberOfIdentifiedUser());
        static::assertSame($errors, $details->numberOfError());
    }

    public function testAllAbsent(): void
    {
        $details = IdentificationDetails::of(null, null, null);

        static::assertNull($details->numberOfDeclaredUser());
        static::assertNull($details->numberOfIdentifiedUser());
        static::assertNull($details->numberOfError());
    }
}
