<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\WithholdingPeriod;

final class PeriodsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidPeriods(): iterable
    {
        yield 'january' => ['2025-01'];
        yield 'september' => ['2025-09'];
        yield 'october' => ['2025-10'];
        yield 'december' => ['2025-12'];
    }

    #[DataProvider('provideValidPeriods')]
    public function testFromString(string $value): void
    {
        $period = WithholdingPeriod::fromString($value);

        static::assertSame($value, $period->value());
        static::assertSame($value, (string) $period);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPeriods(): iterable
    {
        yield 'empty' => [''];
        yield 'month 00' => ['2025-00'];
        yield 'month 13' => ['2025-13'];
        yield 'month 19' => ['2025-19'];
        yield 'month 1 digit' => ['2025-1'];
        yield 'with day' => ['2025-04-15'];
        yield 'short year' => ['25-04'];
        yield 'slash' => ['2025/04'];
        yield 'prefix' => [' 2025-04'];
        yield 'trailing newline' => ["2025-04\n"];
    }

    #[DataProvider('provideInvalidPeriods')]
    public function testFromStringRejectsInvalidInput(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('withholding_period', 'YYYY-MM'));

        WithholdingPeriod::fromString($value);
    }

    public function testEqualsAndCompareTo(): void
    {
        $period = WithholdingPeriod::fromString('2025-04');

        static::assertTrue($period->equals(WithholdingPeriod::fromString('2025-04')));
        static::assertFalse($period->equals(WithholdingPeriod::fromString('2025-05')));
        static::assertSame(0, $period->compareTo(WithholdingPeriod::fromString('2025-04')));
        static::assertSame(-1, $period->compareTo(WithholdingPeriod::fromString('2025-05')));
        static::assertSame(-1, $period->compareTo(WithholdingPeriod::fromString('2026-01')));
        static::assertSame(1, $period->compareTo(WithholdingPeriod::fromString('2025-03')));
        static::assertSame(1, $period->compareTo(WithholdingPeriod::fromString('2024-12')));
    }

    public function testReportedPeriodKeepsTheRawValueAndParsesAValidOne(): void
    {
        $reported = ReportedPeriod::fromWire('2025-04');

        static::assertSame('2025-04', $reported->raw());
        static::assertSame('2025-04', $reported->period()?->value());
    }

    public function testReportedPeriodNeverThrowsOnAnUnexpectedFormat(): void
    {
        $reported = ReportedPeriod::fromWire('avril 2025');

        static::assertSame('avril 2025', $reported->raw());
        static::assertNull($reported->period());
    }
}
