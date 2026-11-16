<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;

final class CalendarDateTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidDates(): iterable
    {
        yield 'regular' => ['2025-04-15'];
        yield 'leap day' => ['2024-02-29'];
        yield 'first day' => ['0001-01-01'];
        yield 'last day' => ['9999-12-31'];
        yield 'thirty days month' => ['2025-04-30'];
    }

    #[DataProvider('provideValidDates')]
    public function testFromString(string $value): void
    {
        $date = CalendarDate::fromString($value);

        static::assertSame($value, $date->format());
        static::assertSame($value, (string) $date);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidDates(): iterable
    {
        yield 'empty' => ['', 'calendar_date.invalid_format'];
        yield 'slashes' => ['2025/04/15', 'calendar_date.invalid_format'];
        yield 'short year' => ['25-04-15', 'calendar_date.invalid_format'];
        yield 'with time' => ['2025-04-15T00:00:00Z', 'calendar_date.invalid_format'];
        yield 'prefix' => [' 2025-04-15', 'calendar_date.invalid_format'];
        yield 'trailing newline' => ["2025-04-15\n", 'calendar_date.invalid_format'];
        yield 'not a leap year' => ['2023-02-29', 'calendar_date.invalid_date'];
        yield 'century not a leap year' => ['1900-02-29', 'calendar_date.invalid_date'];
        yield 'month 13' => ['2025-13-01', 'calendar_date.invalid_date'];
        yield 'month 0' => ['2025-00-10', 'calendar_date.invalid_date'];
        yield 'day 0' => ['2025-04-00', 'calendar_date.invalid_date'];
        yield 'day 31 of a thirty days month' => ['2025-04-31', 'calendar_date.invalid_date'];
        yield 'year 0' => ['0000-01-01', 'calendar_date.invalid_date'];
    }

    #[DataProvider('provideInvalidDates')]
    public function testFromStringRejectsInvalidInput(string $value, string $code): void
    {
        try {
            CalendarDate::fromString($value);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testInvalidMessages(): void
    {
        try {
            CalendarDate::fromString('2023-02-29');
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame('Invalid calendar_date: the date does not exist.', $exception->getMessage());
        }
        $this->expectExceptionObject(InvalidValue::invalidFormat('calendar_date', 'Y-m-d'));

        CalendarDate::fromString('nope');
    }

    public function testEquals(): void
    {
        $date = CalendarDate::fromString('2025-04-15');

        static::assertTrue($date->equals(CalendarDate::fromString('2025-04-15')));
        static::assertFalse($date->equals(CalendarDate::fromString('2025-04-16')));
    }
}
