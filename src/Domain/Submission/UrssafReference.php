<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use Stringable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * Reference ("urssafRef") of a file known to URSSAF; it is not a UUID and is written in a URL path.
 */
final readonly class UrssafReference implements Stringable
{
    private const SUBJECT = 'urssaf_reference';
    public const MAX_LENGTH = 255;

    private function __construct(private string $value) {}

    /**
     * Rejects empty values, values containing a "/" and values longer than {@see self::MAX_LENGTH} characters. The value is kept verbatim.
     *
     * @throws InvalidValue
     */
    public static function fromString(string $value): self
    {
        if ($value === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
        }
        if (str_contains($value, '/')) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'no "/" character');
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

    public function __toString(): string
    {
        return $this->value;
    }
}
