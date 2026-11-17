<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Search;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\Violation;

/**
 * Criteria of a submission search ("search", "start_date", "end_date", "type" plus paging). Every criterion is
 * optional. The API does not state any relation between the two dates, so none is enforced.
 *
 * The "type" criterion applies only to the declaration search (GET /declarations, section 2.7). The identification
 * search (section 2.3) has no such parameter: callers must ignore or refuse it explicitly for identifications.
 */
final readonly class SubmissionSearch
{
    private const MAX_SEARCH_LENGTH = 255;

    private function __construct(
        private PageRequest $pageRequest,
        private ?string $search,
        private ?CalendarDate $startDate,
        private ?CalendarDate $endDate,
        private ?DeclarationType $type,
    ) {}

    /**
     * @throws ValidationFailed "submission_search.search_empty" or "submission_search.search_too_long"
     */
    public static function of(
        PageRequest $pageRequest,
        ?string $search,
        ?CalendarDate $startDate,
        ?CalendarDate $endDate,
        ?DeclarationType $type,
    ): self {
        $result = ValidationResult::valid();
        if ($search === '') {
            $result = $result->with(Violation::error(
                'search',
                'submission_search.search_empty',
                'The search keyword must not be empty when given.',
            ));
        }
        if ($search !== null && mb_strlen($search) > self::MAX_SEARCH_LENGTH) {
            $result = $result->with(Violation::error(
                'search',
                'submission_search.search_too_long',
                \sprintf('The search keyword must not exceed %d characters.', self::MAX_SEARCH_LENGTH),
                ['max_length' => self::MAX_SEARCH_LENGTH],
            ));
        }
        $result->throwIfInvalid();

        return new self($pageRequest, $search, $startDate, $endDate, $type);
    }

    /**
     * No criteria and no paging.
     */
    public static function all(): self
    {
        return new self(PageRequest::firstPage(), null, null, null, null);
    }

    public function pageRequest(): PageRequest
    {
        return $this->pageRequest;
    }

    public function search(): ?string
    {
        return $this->search;
    }

    public function startDate(): ?CalendarDate
    {
        return $this->startDate;
    }

    public function endDate(): ?CalendarDate
    {
        return $this->endDate;
    }

    /**
     * Declaration search only (section 2.7); not a parameter of the identification search (section 2.3).
     */
    public function type(): ?DeclarationType
    {
        return $this->type;
    }
}
