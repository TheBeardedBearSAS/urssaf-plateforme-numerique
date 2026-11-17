<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;

final class DeclarationStatisticsTest extends TestCase
{
    public function testGetters(): void
    {
        $declared = DecimalNumber::fromInt(10);
        $revenue = DecimalNumber::fromFloat(20.152);
        $debit = DecimalNumber::fromInt(3);
        $payment = DecimalNumber::fromInt(4);
        $errors = DecimalNumber::fromInt(0);

        $statistics = DeclarationStatistics::of($declared, $revenue, $debit, $payment, $errors);

        static::assertSame($declared, $statistics->numberOfDeclaredUser());
        static::assertSame($revenue, $statistics->totalRevenue());
        static::assertSame($debit, $statistics->debitAmount());
        static::assertSame($payment, $statistics->paymentAmount());
        static::assertSame($errors, $statistics->numberOfError());
    }

    public function testAllAbsent(): void
    {
        $statistics = DeclarationStatistics::of(null, null, null, null, null);

        static::assertNull($statistics->numberOfDeclaredUser());
        static::assertNull($statistics->totalRevenue());
        static::assertNull($statistics->debitAmount());
        static::assertNull($statistics->paymentAmount());
        static::assertNull($statistics->numberOfError());
    }
}
