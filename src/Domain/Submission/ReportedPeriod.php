<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * A period as reported by the API: the raw string plus the parsed period when it is a valid "YYYY-MM"
 * (an unexpected format never breaks a read, ADR 0006).
 */
final readonly class ReportedPeriod
{
    private function __construct(
        private string $raw,
        private ?WithholdingPeriod $period,
    ) {}

    /**
     * Never throws.
     */
    public static function fromWire(string $raw): self
    {
        try {
            $period = WithholdingPeriod::fromString($raw);
        } catch (InvalidValue) {
            $period = null;
        }

        return new self($raw, $period);
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function period(): ?WithholdingPeriod
    {
        return $this->period;
    }
}
