<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * Pagination figures of a search, from the "UCN-*" response headers; each one is unknown (null) when the header is
 * absent or unreadable.
 */
final readonly class PageInfo
{
    private const SUBJECT = 'page_info';

    private function __construct(
        private ?int $totalPages,
        private ?int $totalCount,
        private ?int $currentPage,
        private ?int $perPage,
    ) {}

    /**
     * @throws InvalidValue "page_info.negative" when a figure is negative
     */
    public static function of(?int $totalPages, ?int $totalCount, ?int $currentPage, ?int $perPage): self
    {
        foreach ([$totalPages, $totalCount, $currentPage, $perPage] as $figure) {
            if ($figure !== null && $figure < 0) {
                throw InvalidValue::negative(self::SUBJECT);
            }
        }

        return new self($totalPages, $totalCount, $currentPage, $perPage);
    }

    public static function unknown(): self
    {
        return new self(null, null, null, null);
    }

    public function totalPages(): ?int
    {
        return $this->totalPages;
    }

    public function totalCount(): ?int
    {
        return $this->totalCount;
    }

    public function currentPage(): ?int
    {
        return $this->currentPage;
    }

    public function perPage(): ?int
    {
        return $this->perPage;
    }
}
