<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\Selection;

final class SelectionTest extends TestCase
{
    private static function item(string $name, int $rank = 0): SelectionItem
    {
        return new SelectionItem($name, $rank);
    }

    public function testNullSortsBeforeAnyInstantAndInstantsAreComparedChronologically(): void
    {
        $early = Instant::fromIso8601('2022-03-01T00:00:00Z');
        $late = Instant::fromIso8601('2022-03-02T00:00:00Z');

        static::assertSame(0, Selection::compareInstants(null, null));
        static::assertSame(-1, Selection::compareInstants(null, $early));
        static::assertSame(1, Selection::compareInstants($early, null));
        static::assertSame(0, Selection::compareInstants($early, Instant::fromIso8601('2022-03-01T00:00:00Z')));
        static::assertSame(-1, Selection::compareInstants($early, $late));
        static::assertSame(1, Selection::compareInstants($late, $early));
    }

    public function testItIsAListWhateverTheKeysOfTheSource(): void
    {
        $a = self::item('a');
        $b = self::item('b');

        static::assertSame([$a, $b], Selection::of(['x' => $a, 'y' => $b])->all());
        static::assertSame([$b], Selection::of(['x' => $a, 'y' => $b])->where(static fn(SelectionItem $i): bool => $i->name === 'b')->all());
    }

    public function testItSortsStably(): void
    {
        $first = self::item('first', 1);
        $second = self::item('second', 0);
        $third = self::item('third', 1);

        $sorted = Selection::of([$first, $second, $third])
            ->sortedBy(static fn(SelectionItem $a, SelectionItem $b): int => $a->rank <=> $b->rank)
            ->all();

        static::assertSame([$second, $first, $third], $sorted);
    }

    public function testItKeepsTheFirstItemOfEachKey(): void
    {
        $a1 = self::item('a');
        $b = self::item('b');
        $a2 = self::item('a');

        $distinct = Selection::of([$a1, $b, $a2])->distinctBy(static fn(SelectionItem $i): string => $i->name)->all();

        static::assertSame([$a1, $b], $distinct);
    }

    public function testItLimits(): void
    {
        $a = self::item('a');
        $b = self::item('b');

        static::assertSame([$a], Selection::of([$a, $b])->limit(1)->all());
        static::assertSame([$a, $b], Selection::of([$a, $b])->limit(5)->all());
    }

    public function testItReverses(): void
    {
        $a = self::item('a');
        $b = self::item('b');
        $c = self::item('c');

        static::assertSame([$c, $b, $a], Selection::of([$a, $b, $c])->reversed()->all());
    }

    public function testItSkipsTheFirstItems(): void
    {
        $a = self::item('a');
        $b = self::item('b');
        $c = self::item('c');

        static::assertSame([$a, $b, $c], Selection::of([$a, $b, $c])->skip(0)->all());
        static::assertSame([$c], Selection::of([$a, $b, $c])->skip(2)->all());
        static::assertSame([], Selection::of([$a, $b, $c])->skip(5)->all());
    }

    public function testCopiesAreDistinctInstancesWithTheSameContent(): void
    {
        $a = self::item('a');

        $copies = Selection::of([$a])->copies();

        static::assertCount(1, $copies);
        static::assertNotSame($a, $copies[0]);
        static::assertEquals($a, $copies[0]);
    }
}
