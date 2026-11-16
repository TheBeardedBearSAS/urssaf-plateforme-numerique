<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

/**
 * What a batch use case did with one submission: its local id and a short outcome word ("changed", "unchanged",
 * "not_found", "adopted", "unmatched", "ambiguous", "manual_review"...).
 */
final readonly class ReportItem
{
    public function __construct(public string $submissionId, public string $outcome) {}
}
