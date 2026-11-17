<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of results with its pagination figures.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class Page implements Countable, IteratorAggregate
{
    /**
     * @param list<T> $items
     */
    private function __construct(
        private array $items,
        private PageInfo $info,
    ) {}

    /**
     * @template U
     *
     * @param list<U> $items
     *
     * @return self<U>
     */
    public static function of(array $items, PageInfo $info): self
    {
        return new self($items, $info);
    }

    /**
     * @return list<T>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function info(): PageInfo
    {
        return $this->info;
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
