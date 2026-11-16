<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use Stringable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * Withholding period "YYYY-MM" (month 01 to 12).
 */
final readonly class WithholdingPeriod implements Stringable
{
    private const SUBJECT = 'withholding_period';
    private const PATTERN = '/^\d{4}-(?:0[1-9]|1[0-2])$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "withholding_period.invalid_format"
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'YYYY-MM');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Chronological comparison: -1 when this period is earlier, 0 when equal, 1 when later.
     */
    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
