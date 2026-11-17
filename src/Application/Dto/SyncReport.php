<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

/**
 * Immutable outcome of a status synchronization run: one item per polled submission, in processing order.
 */
final readonly class SyncReport
{
    /**
     * @param list<ReportItem> $items
     */
    private function __construct(private array $items) {}

    public static function of(ReportItem ...$items): self
    {
        return new self(array_values($items));
    }

    public function with(ReportItem $item): self
    {
        return new self([...$this->items, $item]);
    }

    public function merge(self $other): self
    {
        return new self([...$this->items, ...$other->items]);
    }

    /**
     * @return list<ReportItem> in processing order
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return \count($this->items);
    }

    public function count(string $outcome): int
    {
        return \count(array_filter($this->items, static fn(ReportItem $item): bool => $item->outcome === $outcome));
    }
}
