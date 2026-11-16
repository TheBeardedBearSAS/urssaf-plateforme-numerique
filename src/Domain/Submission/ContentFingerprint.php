<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use Stringable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * SHA-256 digest of an uploaded file, as 64 lowercase hexadecimal characters.
 */
final readonly class ContentFingerprint implements Stringable
{
    private const SUBJECT = 'content_fingerprint';
    private const PATTERN = '/^[0-9a-f]{64}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "content_fingerprint.invalid_format"
     */
    public static function fromHex(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '64 lowercase hexadecimal characters');
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
