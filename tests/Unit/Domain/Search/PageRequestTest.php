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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;

final class PageRequestTest extends TestCase
{
    public function testFirstPage(): void
    {
        $request = PageRequest::firstPage();

        static::assertNull($request->page());
        static::assertNull($request->size());
        static::assertSame([], $request->sorts());
    }

    public function testOf(): void
    {
        $period = SortOrder::of('period', SortDirection::Asc);
        $updated = SortOrder::of('lastUpdateDate', SortDirection::Desc);

        $request = PageRequest::of(2, 50, $period, $updated);

        static::assertSame(2, $request->page());
        static::assertSame(50, $request->size());
        static::assertSame([$period, $updated], $request->sorts());
    }

    public function testSortsAreAlwaysAList(): void
    {
        $period = SortOrder::of('period', SortDirection::Asc);

        $request = PageRequest::of(null, null, ...['first' => $period]);

        static::assertSame([$period], $request->sorts());
    }

    /**
     * @return iterable<string, array{?int, ?int}>
     */
    public static function provideBoundaries(): iterable
    {
        yield 'nothing' => [null, null];
        yield 'first page, smallest size' => [1, 1];
        yield 'largest size' => [1, 500];
    }

    #[DataProvider('provideBoundaries')]
    public function testValidBoundaries(?int $page, ?int $size): void
    {
        $request = PageRequest::of($page, $size);

        static::assertSame($page, $request->page());
        static::assertSame($size, $request->size());
    }

    /**
     * @return iterable<string, array{?int, ?int, list<string>}>
     */
    public static function provideInvalidRequests(): iterable
    {
        yield 'page zero' => [0, null, ['page:page_request.page_not_positive']];
        yield 'page negative' => [-1, 10, ['page:page_request.page_not_positive']];
        yield 'size zero' => [null, 0, ['size:page_request.size_out_of_range']];
        yield 'size negative' => [1, -5, ['size:page_request.size_out_of_range']];
        yield 'size too large' => [1, 501, ['size:page_request.size_out_of_range']];
        yield 'both' => [0, 501, ['page:page_request.page_not_positive', 'size:page_request.size_out_of_range']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidRequests')]
    public function testInvalidRequests(?int $page, ?int $size, array $expected): void
    {
        try {
            PageRequest::of($page, $size);
            static::fail('The request must be refused.');
        } catch (ValidationFailed $validationFailed) {
            $found = [];
            foreach ($validationFailed->result()->errors() as $violation) {
                $found[] = $violation->path() . ':' . $violation->code();
            }
            static::assertSame($expected, $found);
        }
    }

    public function testSizeViolationCarriesTheMaximum(): void
    {
        try {
            PageRequest::of(null, 501);
            static::fail('The request must be refused.');
        } catch (ValidationFailed $validationFailed) {
            $violation = $validationFailed->result()->errors()[0];
            static::assertSame(['max' => 500], $violation->params());
            static::assertSame('The page size must be between 1 and 500.', $violation->message());
        }
    }
}
