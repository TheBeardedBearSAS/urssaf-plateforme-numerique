<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\Payload;

final class PayloadStructureTest extends TestCase
{
    public function testObjectReturnsANestedPayloadOrNull(): void
    {
        $payload = JsonDecoder::decode('{"o":{"a":"b"},"empty":{},"n":null}');

        static::assertSame('b', $payload->object('o')?->string('a'));
        static::assertInstanceOf(Payload::class, $payload->object('empty'));
        static::assertNull($payload->object('n'));
        static::assertNull($payload->object('missing'));
    }

    public function testObjectRejectsScalarsAndNonEmptyLists(): void
    {
        foreach (['"text"', '1', '["a"]', '[{"a":1}]'] as $value) {
            try {
                JsonDecoder::decode('{"o":' . $value . '}')->object('o');
                static::fail('Expected an UnexpectedUrssafResponse for ' . $value);
            } catch (UnexpectedUrssafResponse $exception) {
                static::assertStringContainsString('field "o" is not an object', $exception->getMessage());
            }
        }
    }

    public function testStringsReturnsAListOfStringsOrNothing(): void
    {
        $payload = JsonDecoder::decode('{"l":["a","b"],"e":[],"n":null}');

        static::assertSame(['a', 'b'], $payload->strings('l'));
        static::assertSame([], $payload->strings('e'));
        static::assertSame([], $payload->strings('n'));
        static::assertSame([], $payload->strings('missing'));
    }

    public function testStringsRejectsOtherShapes(): void
    {
        foreach (['"a"', '{"a":"b"}', '["a",1]', '[["a"]]', '[null]'] as $value) {
            try {
                JsonDecoder::decode('{"l":' . $value . '}')->strings('l');
                static::fail('Expected an UnexpectedUrssafResponse for ' . $value);
            } catch (UnexpectedUrssafResponse $exception) {
                static::assertStringContainsString('field "l" is not a list of strings', $exception->getMessage());
            }
        }
    }

    public function testEntriesRejectsAnObject(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('expected a JSON array');

        JsonDecoder::decode('{"a":{"x":1},"b":{"y":2}}')->entries();
    }

    public function testEntriesRejectsListsOfScalars(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('expected a JSON array of objects');

        JsonDecoder::decode('[1,2]')->entries();
    }

    public function testEntriesRejectsListsOfLists(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('expected a JSON array of objects');

        JsonDecoder::decode('[["a"]]')->entries();
    }

    public function testEntriesOfAListOfObjects(): void
    {
        $entries = JsonDecoder::decode('[{"a":"1"},{"a":"2"}]')->entries();

        static::assertSame(['1', '2'], array_map(static fn(Payload $entry): ?string => $entry->string('a'), $entries));
        static::assertSame([], JsonDecoder::decode('[]')->entries());
    }
}
