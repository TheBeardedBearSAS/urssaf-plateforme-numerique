<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Search;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\Violation;

/**
 * Paging and ordering of a search: "page" (from 1), "size" (1 to 500) and "sort". Each one is optional: the API
 * defaults are not published, so an absent value is simply not sent.
 */
final readonly class PageRequest
{
    public const MAX_SIZE = 500;

    /**
     * @param list<SortOrder> $sorts
     */
    private function __construct(
        private ?int $page,
        private ?int $size,
        private array $sorts,
    ) {}

    /**
     * @throws ValidationFailed listing every violation ("page_request.page_not_positive", "page_request.size_out_of_range")
     */
    public static function of(?int $page, ?int $size, SortOrder ...$sorts): self
    {
        $result = ValidationResult::valid();
        if ($page !== null && $page < 1) {
            $result = $result->with(Violation::error(
                'page',
                'page_request.page_not_positive',
                'The page number must be at least 1.',
            ));
        }
        if ($size !== null && ($size < 1 || $size > self::MAX_SIZE)) {
            $result = $result->with(Violation::error(
                'size',
                'page_request.size_out_of_range',
                \sprintf('The page size must be between 1 and %d.', self::MAX_SIZE),
                ['max' => self::MAX_SIZE],
            ));
        }
        $result->throwIfInvalid();

        return new self($page, $size, array_values($sorts));
    }

    /**
     * No page, size nor sort: the API defaults apply.
     */
    public static function firstPage(): self
    {
        return new self(null, null, []);
    }

    public function page(): ?int
    {
        return $this->page;
    }

    public function size(): ?int
    {
        return $this->size;
    }

    /**
     * @return list<SortOrder>
     */
    public function sorts(): array
    {
        return $this->sorts;
    }
}
