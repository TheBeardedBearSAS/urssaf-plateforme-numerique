<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Pagination;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\PageInfo;

final class PageInfoTest extends TestCase
{
    public function testGetters(): void
    {
        $info = PageInfo::of(8, 150, 2, 20);

        static::assertSame(8, $info->totalPages());
        static::assertSame(150, $info->totalCount());
        static::assertSame(2, $info->currentPage());
        static::assertSame(20, $info->perPage());
    }

    public function testZeroIsAccepted(): void
    {
        $info = PageInfo::of(0, 0, 0, 0);

        static::assertSame(0, $info->totalPages());
        static::assertSame(0, $info->totalCount());
        static::assertSame(0, $info->currentPage());
        static::assertSame(0, $info->perPage());
    }

    public function testUnknown(): void
    {
        $info = PageInfo::unknown();

        static::assertNull($info->totalPages());
        static::assertNull($info->totalCount());
        static::assertNull($info->currentPage());
        static::assertNull($info->perPage());
        static::assertNull(PageInfo::of(null, null, null, null)->totalPages());
    }

    /**
     * @return iterable<string, array{?int, ?int, ?int, ?int}>
     */
    public static function provideNegativeFigures(): iterable
    {
        yield 'total pages' => [-1, 1, 1, 1];
        yield 'total count' => [1, -1, 1, 1];
        yield 'current page' => [1, 1, -1, 1];
        yield 'per page' => [1, 1, 1, -1];
        yield 'only per page given' => [null, null, null, -1];
    }

    #[DataProvider('provideNegativeFigures')]
    public function testNegativeFigureIsRefused(?int $totalPages, ?int $totalCount, ?int $currentPage, ?int $perPage): void
    {
        try {
            PageInfo::of($totalPages, $totalCount, $currentPage, $perPage);
            static::fail('A negative figure must be refused.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame('page_info.negative', $invalidValue->violationCode());
        }
    }
}
