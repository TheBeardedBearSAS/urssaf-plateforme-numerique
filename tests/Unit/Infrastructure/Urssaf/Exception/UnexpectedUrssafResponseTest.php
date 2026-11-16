<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

final class UnexpectedUrssafResponseTest extends TestCase
{
    public function testInvalidPayload(): void
    {
        $exception = UnexpectedUrssafResponse::invalidPayload('field "x" is missing');

        static::assertSame('Unexpected URSSAF response: field "x" is missing.', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
    }

    public function testCausedByKeepsThePreviousException(): void
    {
        $previous = new RuntimeException('boom');
        $exception = UnexpectedUrssafResponse::causedBy('body is not valid JSON', $previous);

        static::assertSame('Unexpected URSSAF response: body is not valid JSON.', $exception->getMessage());
        static::assertSame($previous, $exception->getPrevious());
        static::assertSame(0, $exception->getCode());
    }

    public function testUnexpectedStatus(): void
    {
        $exception = UnexpectedUrssafResponse::unexpectedStatus(Operation::GetNewsFeed, 418);

        static::assertSame('Unexpected URSSAF response for operation getPlatformNewsFeed: HTTP 418.', $exception->getMessage());
        static::assertSame(418, $exception->getCode());
    }
}
