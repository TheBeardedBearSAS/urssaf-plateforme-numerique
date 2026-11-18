<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;

final class DecimalNumberTest extends TestCase
{
    public function testFromInt(): void
    {
        static::assertSame('0', DecimalNumber::fromInt(0)->toString());
        static::assertSame('42', DecimalNumber::fromInt(42)->toString());
        static::assertSame('-7', DecimalNumber::fromInt(-7)->toString());
        static::assertSame((string) PHP_INT_MAX, (string) DecimalNumber::fromInt(PHP_INT_MAX));
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function provideFloats(): iterable
    {
        yield 'fraction' => [20.152, '20.152'];
        yield 'zero fraction' => [1.0, '1.0'];
        yield 'zero' => [0.0, '0.0'];
        yield 'negative' => [-3.5, '-3.5'];
        yield 'binary noise is not exposed' => [0.1 + 0.2, '0.30000000000000004'];
        yield 'largest accepted magnitude' => [999_999_999_999_999.0, '999999999999999.0'];
        yield 'smallest accepted magnitude' => [0.0001, '0.0001'];
        yield 'negative zero' => [-0.0, '0.0'];
        yield 'longest fraction (17 significant digits from the 4th decimal)' => [1.234_567_890_123_456_7E-4, '0.00012345678901234567'];
        yield 'large integral' => [123_456_789_012.0, '123456789012.0'];
    }

    #[DataProvider('provideFloats')]
    public function testFromFloat(float $value, string $expected): void
    {
        static::assertSame($expected, DecimalNumber::fromFloat($value)->toString());
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function provideOutOfRangeFloats(): iterable
    {
        yield 'nan' => [NAN];
        yield 'infinity' => [INF];
        yield 'negative infinity' => [-INF];
        yield 'first magnitude with an exponent' => [1.0e15];
        yield 'last magnitude with an exponent' => [0.000_09];
        yield 'large exponent' => [1.0e25];
        yield 'small exponent' => [1.0e-10];
        yield 'negative large exponent' => [-1.5e30];
    }

    #[DataProvider('provideOutOfRangeFloats')]
    public function testFromFloatRejectsOutOfRangeValues(float $value): void
    {
        try {
            DecimalNumber::fromFloat($value);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame('decimal_number.out_of_range', $exception->violationCode());
            static::assertSame(
                'Invalid decimal_number: the float is not finite or cannot be written without an exponent.',
                $exception->getMessage(),
            );
        }
    }

    public function testEqualsComparesCanonicalForms(): void
    {
        $number = DecimalNumber::fromString('1.5');

        static::assertTrue($number->equals(DecimalNumber::fromFloat(1.5)));
        static::assertFalse($number->equals(DecimalNumber::fromString('1.50')));
        static::assertFalse(DecimalNumber::fromInt(1)->equals(DecimalNumber::fromFloat(1.0)));
        static::assertTrue(DecimalNumber::fromInt(1)->equals(DecimalNumber::fromString('1')));
    }
}
