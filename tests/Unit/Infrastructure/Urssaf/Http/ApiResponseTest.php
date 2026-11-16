<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\PsrBinaryStream;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\JsonDecoder;

final class ApiResponseTest extends TestCase
{
    public function testAJsonResponseExposesItsPayloadHeadersAndRequestId(): void
    {
        $payload = JsonDecoder::decode('{"a":"b"}');

        $response = ApiResponse::json($payload, ['ucn-total-count' => '12'], 'request-id-1');

        static::assertSame($payload, $response->payload());
        static::assertFalse($response->isStream());
        static::assertSame('12', $response->header('UCN-Total-Count'));
        static::assertSame('12', $response->header('ucn-total-count'));
        static::assertNull($response->header('Content-Type'));
        static::assertSame('request-id-1', $response->requestId());
    }

    public function testAJsonResponseHasNoStream(): void
    {
        $response = ApiResponse::json(JsonDecoder::decode('{}'), [], 'request-id-1');

        $this->expectException(LogicException::class);

        $response->stream();
    }

    public function testABinaryResponseExposesItsStreamAndHeaders(): void
    {
        $stream = new PsrBinaryStream(Stream::create('abc'), new Psr17Factory());

        $response = ApiResponse::binary($stream, ['content-type' => 'application/octet-stream'], 'request-id-2');

        static::assertSame($stream, $response->stream());
        static::assertTrue($response->isStream());
        static::assertSame('application/octet-stream', $response->header('Content-Type'));
        static::assertSame('request-id-2', $response->requestId());
    }

    public function testABinaryResponseHasNoPayload(): void
    {
        $response = ApiResponse::binary(new PsrBinaryStream(Stream::create(''), new Psr17Factory()), [], 'request-id-2');

        $this->expectException(LogicException::class);

        $response->payload();
    }
}
