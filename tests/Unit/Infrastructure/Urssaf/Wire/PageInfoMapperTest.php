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
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\PageInfoMapper;

final class PageInfoMapperTest extends TestCase
{
    public function testItReadsTheFourHeaders(): void
    {
        $info = PageInfoMapper::fromHeaders('8', '150', '2', '20');

        static::assertSame(8, $info->totalPages());
        static::assertSame(150, $info->totalCount());
        static::assertSame(2, $info->currentPage());
        static::assertSame(20, $info->perPage());
    }

    public function testAbsentHeadersAreUnknown(): void
    {
        $info = PageInfoMapper::fromHeaders(null, null, null, null);

        static::assertNull($info->totalPages());
        static::assertNull($info->totalCount());
        static::assertNull($info->currentPage());
        static::assertNull($info->perPage());
    }

    public function testEachHeaderIsReadIndependently(): void
    {
        $info = PageInfoMapper::fromHeaders('x', '150', null, '20');

        static::assertNull($info->totalPages());
        static::assertSame(150, $info->totalCount());
        static::assertNull($info->currentPage());
        static::assertSame(20, $info->perPage());
    }

    public function testZeroAndLeadingZerosAreIntegers(): void
    {
        $info = PageInfoMapper::fromHeaders('0', '007', '1', '500');

        static::assertSame(0, $info->totalPages());
        static::assertSame(7, $info->totalCount());
    }

    public function testTheLargestAcceptedFigureHasEighteenDigits(): void
    {
        static::assertSame(999_999_999_999_999_999, PageInfoMapper::fromHeaders('999999999999999999', null, null, null)->totalPages());
        static::assertNull(PageInfoMapper::fromHeaders('1000000000000000000', null, null, null)->totalPages());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableFigures(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['many'];
        yield 'negative' => ['-1'];
        yield 'decimal' => ['1.5'];
        yield 'signed' => ['+1'];
        yield 'spaces' => [' 1'];
        yield 'trailing newline' => ["1\n"];
        yield 'exponent' => ['1e3'];
    }

    #[DataProvider('unreadableFigures')]
    public function testAnythingButDigitsIsUnknown(string $header): void
    {
        $info = PageInfoMapper::fromHeaders($header, $header, $header, $header);

        static::assertNull($info->totalPages());
        static::assertNull($info->totalCount());
        static::assertNull($info->currentPage());
        static::assertNull($info->perPage());
    }
}
