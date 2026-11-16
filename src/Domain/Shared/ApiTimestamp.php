<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared;

use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * A timestamp whose format is not published: the raw string plus a best-effort instant (ADR 0006).
 */
final readonly class ApiTimestamp
{
    private function __construct(
        private string $raw,
        private ?Instant $instant,
    ) {}

    /**
     * Never throws: an unparseable value only leaves {@see self::instant()} null.
     */
    public static function of(string $raw, DateTimeZone $assumedZone): self
    {
        try {
            $instant = Instant::fromLocalDateTime($raw, $assumedZone);
        } catch (InvalidValue) {
            $instant = null;
        }

        return new self($raw, $instant);
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function instant(): ?Instant
    {
        return $this->instant;
    }
}
