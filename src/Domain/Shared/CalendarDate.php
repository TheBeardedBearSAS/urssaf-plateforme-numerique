<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared;

use Stringable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * Date without time nor time zone, "Y-m-d", years 0001 to 9999 (search criteria "start_date" / "end_date").
 */
final readonly class CalendarDate implements Stringable
{
    private const SUBJECT = 'calendar_date';
    private const PATTERN = '/^\d{4}-\d{2}-\d{2}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "calendar_date.invalid_format" or "calendar_date.invalid_date" (e.g. 2023-02-29)
     */
    public static function fromString(string $date): self
    {
        if (preg_match(self::PATTERN, $date) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'Y-m-d');
        }
        [$year, $month, $day] = array_map(intval(...), explode('-', $date));
        if (!checkdate($month, $day, $year)) {
            throw InvalidValue::because(self::SUBJECT . '.invalid_date', 'Invalid calendar_date: the date does not exist.');
        }

        return new self($date);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * "Y-m-d".
     */
    public function format(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
