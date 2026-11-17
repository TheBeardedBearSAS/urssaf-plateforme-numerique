<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Shared;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

final class InstantParsingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideIsoStrings(): iterable
    {
        yield 'zulu' => ['2022-02-01T16:40:00Z', '2022-02-01T16:40:00Z'];
        yield 'milliseconds' => ['1980-03-29T00:00:00.000Z', '1980-03-29T00:00:00Z'];
        yield 'microseconds' => ['1980-03-29T00:00:00.123456Z', '1980-03-29T00:00:00Z'];
        yield 'one fraction digit' => ['1980-03-29T00:00:00.5Z', '1980-03-29T00:00:00Z'];
        yield 'positive offset' => ['2022-02-01T17:40:00+01:00', '2022-02-01T16:40:00Z'];
        yield 'negative offset' => ['2022-02-01T11:40:00-05:00', '2022-02-01T16:40:00Z'];
    }

    #[DataProvider('provideIsoStrings')]
    public function testFromIso8601(string $value, string $expected): void
    {
        static::assertSame($expected, Instant::fromIso8601($value)->format());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidIsoStrings(): iterable
    {
        yield 'no offset' => ['2022-02-01T16:40:00', 'instant.invalid_format'];
        yield 'date only' => ['2022-02-01', 'instant.invalid_format'];
        yield 'space separator' => ['2022-02-01 16:40:00Z', 'instant.invalid_format'];
        yield 'prefix' => ['x2022-02-01T16:40:00Z', 'instant.invalid_format'];
        yield 'trailing newline' => ["2022-02-01T16:40:00Z\n", 'instant.invalid_format'];
        yield 'too many fraction digits' => ['2022-02-01T16:40:00.1234567Z', 'instant.invalid_format'];
        yield 'empty fraction' => ['2022-02-01T16:40:00.Z', 'instant.invalid_format'];
        yield 'rolled over day' => ['2022-02-30T16:40:00Z', 'instant.invalid_date'];
        yield 'rolled over minute' => ['2022-02-01T16:60:00Z', 'instant.invalid_date'];
        yield 'unparseable month' => ['2022-13-01T16:40:00Z', 'instant.invalid_date'];
    }

    #[DataProvider('provideInvalidIsoStrings')]
    public function testFromIso8601RejectsInvalidInput(string $value, string $code): void
    {
        try {
            Instant::fromIso8601($value);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testInvalidMessages(): void
    {
        try {
            Instant::fromIso8601('2022-02-30T16:40:00Z');
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame('Invalid instant: the date does not exist.', $exception->getMessage());
        }
        $this->expectExceptionObject(InvalidValue::invalidFormat('instant', 'ISO 8601 date-time with offset'));

        Instant::fromIso8601('nope');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLocalDateTimes(): iterable
    {
        yield 'no offset, winter' => ['2022-02-01T17:40:00', '2022-02-01T16:40:00Z'];
        yield 'no offset, summer' => ['2022-07-01T17:40:00', '2022-07-01T15:40:00Z'];
        yield 'fraction' => ['2022-02-01T17:40:00.1', '2022-02-01T16:40:00Z'];
        yield 'microseconds' => ['2022-02-01T17:40:00.123456', '2022-02-01T16:40:00Z'];
        yield 'zulu wins' => ['2022-02-01T17:40:00Z', '2022-02-01T17:40:00Z'];
        yield 'zulu with fraction' => ['2022-02-01T17:40:00.250Z', '2022-02-01T17:40:00Z'];
        yield 'positive offset wins' => ['2022-02-01T17:40:00+05:00', '2022-02-01T12:40:00Z'];
        yield 'negative offset wins' => ['2022-02-01T17:40:00.5-05:00', '2022-02-01T22:40:00Z'];
        yield 'offset at the +14:00 bound' => ['2022-02-01T17:40:00+14:00', '2022-02-01T03:40:00Z'];
        yield 'offset at the -14:59 bound' => ['2022-02-01T17:40:00-14:59', '2022-02-02T08:39:00Z'];
        yield 'DST gap is resolved forward' => ['2026-03-29T02:30:00', '2026-03-29T01:30:00Z'];
        yield 'DST overlap' => ['2026-10-25T02:30:00', '2026-10-25T01:30:00Z'];
    }

    #[DataProvider('provideLocalDateTimes')]
    public function testFromLocalDateTime(string $value, string $expected): void
    {
        static::assertSame($expected, Instant::fromLocalDateTime($value, new DateTimeZone('Europe/Paris'))->format());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidLocalDateTimes(): iterable
    {
        yield 'empty' => [''];
        yield 'date only' => ['2022-02-01'];
        yield 'space separator' => ['2022-02-01 16:40:00'];
        yield 'prefix' => ['x2022-02-01T16:40:00'];
        yield 'trailing newline' => ["2022-02-01T16:40:00\n"];
        yield 'too many fraction digits' => ['2022-02-01T16:40:00.1234567'];
        yield 'empty fraction' => ['2022-02-01T16:40:00.'];
        yield 'offset without colon' => ['2022-02-01T16:40:00+0100'];
        yield 'no seconds' => ['2022-02-01T16:40'];
        yield 'rolled over day' => ['2022-02-30T16:40:00'];
        yield 'rolled over minute' => ['2022-02-01T16:60:00Z'];
        yield 'unparseable month' => ['2022-13-01T16:40:00'];
        yield 'rolled over day, no zone shift' => ['2025-02-30T10:00:00'];
        yield 'offset beyond +14:00' => ['2025-04-28T20:04:14+24:00'];
        yield 'offset beyond -14:59' => ['2025-04-28T20:04:14-15:00'];
        yield 'offset minutes beyond 59' => ['2025-04-28T20:04:14+01:60'];
    }

    #[DataProvider('provideInvalidLocalDateTimes')]
    public function testFromLocalDateTimeRejectsInvalidInput(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('instant', 'ISO 8601 date-time'));

        Instant::fromLocalDateTime($value, new DateTimeZone('Europe/Paris'));
    }
}
