<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Shared;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ApiTimestamp;

final class ApiTimestampTest extends TestCase
{
    public function testParsableValueGivesAnInstantInTheAssumedZone(): void
    {
        $timestamp = ApiTimestamp::of('2022-02-01T17:40:00.123', new DateTimeZone('Europe/Paris'));

        static::assertSame('2022-02-01T17:40:00.123', $timestamp->raw());
        static::assertSame('2022-02-01T16:40:00Z', $timestamp->instant()?->format());
    }

    public function testExplicitOffsetWins(): void
    {
        $timestamp = ApiTimestamp::of('2022-02-01T17:40:00Z', new DateTimeZone('Europe/Paris'));

        static::assertSame('2022-02-01T17:40:00Z', $timestamp->instant()?->format());
    }

    public function testUnparsableValueIsKeptRawWithoutInstant(): void
    {
        $timestamp = ApiTimestamp::of('01/02/2022 17h40', new DateTimeZone('Europe/Paris'));

        static::assertSame('01/02/2022 17h40', $timestamp->raw());
        static::assertNull($timestamp->instant());
    }
}
