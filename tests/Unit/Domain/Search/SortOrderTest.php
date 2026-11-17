<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;

final class SortOrderTest extends TestCase
{
    public function testDirectionWireValues(): void
    {
        static::assertSame('ASC', SortDirection::Asc->value);
        static::assertSame('DESC', SortDirection::Desc->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidFields(): iterable
    {
        yield 'period' => ['period'];
        yield 'camel case' => ['lastUpdateDate'];
        yield 'dotted' => ['a.b_c'];
        yield 'digits' => ['field1'];
        yield 'single character' => ['a'];
        yield 'max length' => [str_repeat('a', 64)];
    }

    #[DataProvider('provideValidFields')]
    public function testOf(string $field): void
    {
        $order = SortOrder::of($field, SortDirection::Desc);

        static::assertSame($field, $order->field());
        static::assertSame(SortDirection::Desc, $order->direction());
        static::assertSame($field . ':DESC', $order->toWire());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidFields(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'space' => ['a b'];
        yield 'colon' => ['a:b'];
        yield 'dash' => ['a-b'];
        yield 'trailing newline' => ["period\n"];
        yield 'accent' => ['périod'];
    }

    #[DataProvider('provideInvalidFields')]
    public function testInvalidField(string $field): void
    {
        try {
            SortOrder::of($field, SortDirection::Asc);
            static::fail('The field must be refused.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame('sort_order.invalid_field', $invalidValue->violationCode());
            static::assertSame(['max_length' => 64], $invalidValue->params());
        }
    }

    public function testFromWire(): void
    {
        $order = SortOrder::fromWire('lastUpdateDate:DESC');

        static::assertSame('lastUpdateDate', $order->field());
        static::assertSame(SortDirection::Desc, $order->direction());
        static::assertSame('period:ASC', SortOrder::fromWire('period:ASC')->toWire());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidWireValues(): iterable
    {
        yield 'no separator' => ['period', 'sort_order.invalid_format'];
        yield 'empty' => ['', 'sort_order.invalid_format'];
        yield 'unknown direction' => ['period:UP', 'sort_order.invalid_format'];
        yield 'lower case direction' => ['period:asc', 'sort_order.invalid_format'];
        yield 'missing direction' => ['period:', 'sort_order.invalid_format'];
        yield 'missing field' => [':ASC', 'sort_order.invalid_field'];
        yield 'invalid field' => ['a b:ASC', 'sort_order.invalid_field'];
        yield 'two separators' => ['a:b:ASC', 'sort_order.invalid_field'];
    }

    #[DataProvider('provideInvalidWireValues')]
    public function testInvalidWireValue(string $value, string $code): void
    {
        try {
            SortOrder::fromWire($value);
            static::fail('The value must be refused.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame($code, $invalidValue->violationCode());
        }
    }
}
