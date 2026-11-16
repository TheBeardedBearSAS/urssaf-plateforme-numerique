<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Shared;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

final class InstantTest extends TestCase
{
    public function testFromDateTimeNormalisesToUtcAndTruncatesMicroseconds(): void
    {
        $instant = Instant::fromDateTime(new DateTimeImmutable('2022-02-01 17:40:00.987654', new DateTimeZone('Europe/Paris')));

        static::assertSame('2022-02-01T16:40:00Z', $instant->format());
        static::assertSame('UTC', $instant->toDateTimeImmutable()->getTimezone()->getName());
        static::assertSame('2022-02-01 16:40:00.000000', $instant->toDateTimeImmutable()->format('Y-m-d H:i:s.u'));
    }

    public function testPlusAndMinus(): void
    {
        $instant = Instant::fromIso8601('2022-02-01T16:40:00Z');

        static::assertSame('2022-02-01T16:50:00Z', $instant->plus(new DateInterval('PT10M'))->format());
        static::assertSame('2022-02-01T16:30:00Z', $instant->minus(new DateInterval('PT10M'))->format());
        static::assertSame('2023-02-01T16:40:00Z', $instant->plus(new DateInterval('P1Y'))->format());
        static::assertSame('2021-02-01T16:40:00Z', $instant->minus(new DateInterval('P1Y'))->format());
        static::assertSame('2022-02-01T16:40:00Z', $instant->format());
    }

    public function testComparisons(): void
    {
        $instant = Instant::fromIso8601('2022-02-01T16:40:00Z');
        $earlier = Instant::fromIso8601('2022-02-01T16:39:59Z');
        $later = Instant::fromIso8601('2022-02-01T16:40:01Z');
        $same = Instant::fromIso8601('2022-02-01T17:40:00.500+01:00');

        static::assertTrue($instant->isAfter($earlier));
        static::assertFalse($instant->isAfter($same));
        static::assertFalse($instant->isAfter($later));
        static::assertTrue($instant->isBefore($later));
        static::assertFalse($instant->isBefore($same));
        static::assertFalse($instant->isBefore($earlier));
        static::assertTrue($instant->equals($same));
        static::assertFalse($instant->equals($later));
        static::assertFalse($instant->equals($earlier));
    }
}
