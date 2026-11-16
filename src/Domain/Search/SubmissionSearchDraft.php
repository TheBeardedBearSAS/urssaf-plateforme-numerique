<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Search;

/**
 * Raw, untrusted search criteria (query string, form, CLI options) to be checked by {@see SubmissionSearchParser}.
 */
final readonly class SubmissionSearchDraft
{
    /**
     * @param list<string> $sort "field:ASC" / "field:DESC" entries
     */
    public function __construct(
        public ?int $page,
        public ?int $size,
        public array $sort,
        public ?string $search,
        public ?string $startDate,
        public ?string $endDate,
        public ?string $type,
    ) {}
}
