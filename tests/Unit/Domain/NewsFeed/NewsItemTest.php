<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\NewsFeed;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

final class NewsItemTest extends TestCase
{
    public function testGetters(): void
    {
        $date = Instant::fromIso8601('2024-05-01T10:00:00.123456+02:00');

        $item = NewsItem::of('news-1', 'Scheduled maintenance.', $date);

        static::assertSame('news-1', $item->idNews());
        static::assertSame('Scheduled maintenance.', $item->newsText());
        static::assertSame($date, $item->newsDate());
    }

    public function testAllAbsent(): void
    {
        $item = NewsItem::of(null, null, null);

        static::assertNull($item->idNews());
        static::assertNull($item->newsText());
        static::assertNull($item->newsDate());
    }
}
