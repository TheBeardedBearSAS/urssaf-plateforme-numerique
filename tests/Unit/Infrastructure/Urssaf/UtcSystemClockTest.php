<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UtcSystemClock;

final class UtcSystemClockTest extends TestCase
{
    public function testItReturnsTheCurrentTimeInUtc(): void
    {
        $before = time();

        $now = (new UtcSystemClock())->now();

        static::assertSame('UTC', $now->getTimezone()->getName());
        static::assertGreaterThanOrEqual($before, $now->getTimestamp());
        static::assertLessThanOrEqual(time(), $now->getTimestamp());
    }
}
