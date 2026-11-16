<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * Point on the time line, normalised to UTC with second precision.
 */
final readonly class Instant
{
    private const SUBJECT = 'instant';
    private const ISO_PATTERN = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D';
    private const LOCAL_PATTERN = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.\d{1,6})?(Z|[+-](?:0\d|1[0-4]):[0-5]\d)?$/D';

    private DateTimeImmutable $utc;

    private function __construct(DateTimeInterface $dateTime)
    {
        $this->utc = (new DateTimeImmutable('@' . $dateTime->getTimestamp()))->setTimezone(new DateTimeZone('UTC'));
    }

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return new self($dateTime);
    }

    /**
     * Strict ISO 8601 with an explicit offset: "2022-02-01T16:40:00Z", "2022-02-01T16:40:00.000+01:00".
     * Fractional seconds are accepted and truncated.
     *
     * @throws InvalidValue
     */
    public static function fromIso8601(string $value): self
    {
        $matches = [];
        if (preg_match(self::ISO_PATTERN, $value, $matches) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'ISO 8601 date-time with offset');
        }
        $parsed = date_create_immutable($value);
        if ($parsed === false || $parsed->format('Y-m-d\TH:i:s') !== $matches[1]) {
            throw InvalidValue::because(self::SUBJECT . '.invalid_date', 'Invalid instant: the date does not exist.');
        }

        return new self($parsed);
    }

    /**
     * ISO 8601 date-time with an optional fraction and an optional offset ("Z" or "+HH:MM"): an explicit offset
     * wins, otherwise the value is read in the assumed zone (ADR 0006).
     *
     * @throws InvalidValue "instant.invalid_format"
     */
    public static function fromLocalDateTime(string $value, DateTimeZone $assumedZone): self
    {
        $matches = [];
        if (preg_match(self::LOCAL_PATTERN, $value, $matches) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'ISO 8601 date-time');
        }
        // The calendar fields are checked apart from the zone: a wall-clock time in a DST gap is moved forward by PHP.
        $calendar = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $matches[1], new DateTimeZone('UTC'));
        if ($calendar === false || $calendar->format('Y-m-d\TH:i:s') !== $matches[1]) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'ISO 8601 date-time');
        }
        // The pattern and the calendar check above guarantee that PHP can parse the value.
        return new self(new DateTimeImmutable($value, $assumedZone));
    }

    public function toDateTimeImmutable(): DateTimeImmutable
    {
        return $this->utc;
    }

    /**
     * "2022-02-01T16:40:00Z".
     */
    public function format(): string
    {
        return $this->utc->format('Y-m-d\TH:i:s\Z');
    }

    public function plus(DateInterval $interval): self
    {
        return new self($this->utc->add($interval));
    }

    public function minus(DateInterval $interval): self
    {
        return new self($this->utc->sub($interval));
    }

    public function isBefore(self $other): bool
    {
        return $this->utc->getTimestamp() < $other->utc->getTimestamp();
    }

    public function isAfter(self $other): bool
    {
        return $this->utc->getTimestamp() > $other->utc->getTimestamp();
    }

    public function equals(self $other): bool
    {
        return $this->utc->getTimestamp() === $other->utc->getTimestamp();
    }
}
