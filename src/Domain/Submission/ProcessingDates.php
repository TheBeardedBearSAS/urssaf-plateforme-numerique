<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ApiTimestamp;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

/**
 * The dates URSSAF reports for a file (ADR 0006): "lastUpdateDate" is a date-time, the two others have no published
 * format and are kept raw.
 */
final readonly class ProcessingDates
{
    private function __construct(
        private ?Instant $lastUpdateDate,
        private ?ApiTimestamp $uploadDate,
        private ?ApiTimestamp $treatmentDate,
    ) {}

    public static function of(?Instant $lastUpdateDate, ?ApiTimestamp $uploadDate, ?ApiTimestamp $treatmentDate): self
    {
        return new self($lastUpdateDate, $uploadDate, $treatmentDate);
    }

    public function lastUpdateDate(): ?Instant
    {
        return $this->lastUpdateDate;
    }

    public function uploadDate(): ?ApiTimestamp
    {
        return $this->uploadDate;
    }

    public function treatmentDate(): ?ApiTimestamp
    {
        return $this->treatmentDate;
    }
}
