<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\Payload;

final class PayloadTest extends TestCase
{
    public function testItRejectsADecodedScalar(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('body is neither a JSON object nor a JSON array');

        Payload::fromDecoded('text');
    }

    public function testItDistinguishesListsFromObjects(): void
    {
        static::assertTrue(JsonDecoder::decode('[{"a":1}]')->isList());
        static::assertTrue(JsonDecoder::decode('[]')->isList());
        static::assertFalse(JsonDecoder::decode('{"a":1}')->isList());
    }

    public function testStringReturnsOnlyStrings(): void
    {
        $payload = JsonDecoder::decode('{"s":"x","i":1,"n":null,"a":[],"b":true}');

        static::assertSame('x', $payload->string('s'));
        static::assertNull($payload->string('i'));
        static::assertNull($payload->string('n'));
        static::assertNull($payload->string('a'));
        static::assertNull($payload->string('b'));
        static::assertNull($payload->string('missing'));
    }

    public function testOptionalStringReturnsAStringOrNull(): void
    {
        $payload = JsonDecoder::decode('{"s":"x","e":"","n":null}');

        static::assertSame('x', $payload->optionalString('s'));
        static::assertSame('', $payload->optionalString('e'));
        static::assertNull($payload->optionalString('n'));
        static::assertNull($payload->optionalString('missing'));
    }

    public function testOptionalStringRejectsOtherTypes(): void
    {
        foreach (['1', '1.5', 'true', '[]', '{"a":1}'] as $value) {
            try {
                JsonDecoder::decode('{"s":' . $value . '}')->optionalString('s');
                static::fail('Expected an UnexpectedUrssafResponse for ' . $value);
            } catch (UnexpectedUrssafResponse $exception) {
                static::assertStringContainsString('field "s" is not a string', $exception->getMessage());
            }
        }
    }

    public function testRequireStringReturnsANonEmptyString(): void
    {
        static::assertSame('x', JsonDecoder::decode('{"s":"x"}')->requireString('s'));
    }

    public function testRequireStringRejectsMissingEmptyAndNonStringValues(): void
    {
        foreach (['{}', '{"s":""}', '{"s":1}'] as $json) {
            try {
                JsonDecoder::decode($json)->requireString('s');
                static::fail('Expected an UnexpectedUrssafResponse for ' . $json);
            } catch (UnexpectedUrssafResponse $exception) {
                static::assertStringContainsString('field "s" is missing or empty', $exception->getMessage());
            }
        }
    }

    public function testNumberReturnsNumbersBigIntegerStringsAndNullWhenAbsent(): void
    {
        $payload = JsonDecoder::decode('{"i":2000,"f":11.02,"n":null,"big":12345678901234567890}');

        static::assertSame(2000, $payload->number('i'));
        static::assertSame(11.02, $payload->number('f'));
        static::assertSame('12345678901234567890', $payload->number('big'));
        static::assertNull($payload->number('n'));
        static::assertNull($payload->number('missing'));
    }

    public function testNumberRejectsOtherTypes(): void
    {
        foreach (['true', '[]', '{"a":1}'] as $value) {
            try {
                JsonDecoder::decode('{"n":' . $value . '}')->number('n');
                static::fail('Expected an UnexpectedUrssafResponse for ' . $value);
            } catch (UnexpectedUrssafResponse $exception) {
                static::assertStringContainsString('field "n" is not a number', $exception->getMessage());
            }
        }
    }
}
