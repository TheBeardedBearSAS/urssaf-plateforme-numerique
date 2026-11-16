<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\ErrorPayloadParser;

final class ErrorPayloadParserTest extends TestCase
{
    public function testItParsesTheDocumentedArrayOfErrors(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody(WireFixtures::load('error_response_handbuilt'));

        static::assertSame(['ERR_EXAMPLE_001', 'ERR_EXAMPLE_002'], $errors->codes());
        static::assertSame('A message describing the error', $errors->all()[0]->message());
        static::assertSame('A description of the error', $errors->all()[0]->description());
        static::assertSame('Another description', $errors->all()[1]->description());
    }

    public function testItParsesASingleErrorObjectAndTrimsTheCode(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('{"code":" ERR_X ","message":"m","description":"d"}');

        static::assertSame(['ERR_X'], $errors->codes());
        static::assertSame('m', $errors->all()[0]->message());
        static::assertSame('d', $errors->all()[0]->description());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'html' => ['<html>Bad gateway</html>'];
        yield 'scalar' => ['"oops"'];
        yield 'empty object' => ['{}'];
        yield 'empty list' => ['[]'];
        yield 'list of scalars' => ['[1, 2]'];
        yield 'object without code' => ['{"message":"m"}'];
        yield 'object with a numeric code' => ['{"code":12}'];
    }

    #[DataProvider('unreadableBodies')]
    public function testItIsLenientWithUnreadableBodies(string $body): void
    {
        static::assertTrue((new ErrorPayloadParser())->parseBody($body)->isEmpty());
    }

    public function testEntriesWithoutACodeAreIgnoredAndMissingTextsDefaultToEmpty(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('[{"message":"no code"},{"code":""},{"code":"  "},{"code":"ERR_X"}]');

        static::assertSame(['ERR_X'], $errors->codes());
        static::assertSame('', $errors->all()[0]->message());
        static::assertSame('', $errors->all()[0]->description());
    }

    public function testParsePayloadIsStrictOnAListOfScalars(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new ErrorPayloadParser())->parsePayload(WireFixtures::payload([1, 2]));
    }
}
