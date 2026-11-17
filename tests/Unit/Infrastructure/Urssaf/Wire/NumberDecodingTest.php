<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\NumberDecoding;

final class NumberDecodingTest extends TestCase
{
    public function testItDecodesIntegersAndFloats(): void
    {
        static::assertSame('12', NumberDecoding::decimal(12)->toString());
        static::assertSame('-3', NumberDecoding::decimal(-3)->toString());
        static::assertSame('3350.71', NumberDecoding::decimal(3350.71)->toString());
        static::assertSame('12.0', NumberDecoding::decimal(12.0)->toString());
    }

    public function testItAcceptsABigIntegerString(): void
    {
        static::assertSame('12345678901234567890', NumberDecoding::decimal('12345678901234567890')->toString());
        static::assertSame('-12345678901234567890', NumberDecoding::decimal('-12345678901234567890')->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedStrings(): iterable
    {
        yield 'small integer' => ['12'];
        yield 'text' => ['abc'];
        yield 'empty' => [''];
        yield 'decimal' => ['1234567890123456789.5'];
        yield 'leading zero' => ['0123456789012345678'];
        yield 'trailing newline' => ["12345678901234567890\n"];
        yield 'eighteen digits' => ['123456789012345678'];
        yield 'nineteen digits within the int range' => ['1000000000000000000'];
        yield 'negative nineteen digits within the int range' => ['-1000000000000000000'];
    }

    #[DataProvider('rejectedStrings')]
    public function testItRejectsAnyOtherString(string $value): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('a number is expected');

        NumberDecoding::decimal($value);
    }

    public function testItRejectsAFloatWithoutADecimalRepresentation(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('number is out of range');

        NumberDecoding::decimal(1.0e20);
    }

    public function testFieldReadsANumberOrNull(): void
    {
        $payload = WireFixtures::payload(['a' => 5, 'b' => 1.5, 'c' => null]);

        static::assertSame('5', NumberDecoding::field($payload, 'a')?->toString());
        static::assertSame('1.5', NumberDecoding::field($payload, 'b')?->toString());
        static::assertNull(NumberDecoding::field($payload, 'c'));
        static::assertNull(NumberDecoding::field($payload, 'missing'));
    }

    public function testFieldRejectsTextAndBooleans(): void
    {
        foreach (['"12"', 'true'] as $value) {
            $rejected = false;

            try {
                NumberDecoding::field(WireFixtures::payload(['n' => json_decode($value)]), 'n');
            } catch (UnexpectedUrssafResponse) {
                $rejected = true;
            }

            static::assertTrue($rejected, $value);
        }
    }
}
