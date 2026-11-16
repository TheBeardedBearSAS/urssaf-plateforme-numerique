<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Pagination;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\PageInfo;

final class PageTest extends TestCase
{
    public function testItemsInfoCountAndIteration(): void
    {
        $info = PageInfo::of(2, 3, 1, 2);
        $page = Page::of(['a', 'b'], $info);

        static::assertSame(['a', 'b'], $page->items());
        static::assertSame($info, $page->info());
        static::assertCount(2, $page);
        static::assertFalse($page->isEmpty());
        static::assertSame(['a', 'b'], iterator_to_array($page, true));
    }

    public function testEmptyPage(): void
    {
        $page = Page::of([], PageInfo::unknown());

        static::assertSame([], $page->items());
        static::assertCount(0, $page);
        static::assertTrue($page->isEmpty());
        static::assertSame([], iterator_to_array($page, true));
    }
}
