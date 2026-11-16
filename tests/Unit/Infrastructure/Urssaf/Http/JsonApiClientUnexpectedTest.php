<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

final class JsonApiClientUnexpectedTest extends TestCase
{
    private function call(int $status, string $body): ApiResponse
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond($status, $body);

        return $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::SearchDeclarations, '/p', []),
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unexpectedStatuses(): iterable
    {
        yield 'informational' => [199];
        yield 'multiple choices' => [300];
        yield 'redirect' => [302];
        yield 'teapot' => [418];
        yield 'conflict' => [409];
        yield 'too early' => [425];
    }

    #[DataProvider('unexpectedStatuses')]
    public function testAnyOtherStatusIsUnexpected(int $status): void
    {
        try {
            $this->call($status, '{}');
            static::fail('Expected an unexpected-response exception.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertContains(UrssafException::class, class_implements($exception));
            static::assertSame($status, $exception->getCode());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableSuccessBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'not json' => ['<html>'];
        yield 'scalar' => ['"ok"'];
    }

    #[DataProvider('unusableSuccessBodies')]
    public function testASuccessWithAnUnusableBodyIsUnexpected(string $body): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        $this->call(200, $body);
    }

    public function testTransportErrorsAreRetryableAndKeepOnlyTheCauseClass(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout on https://api.example.test/p?search=secret'));

        try {
            $stack->jsonClient()->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                ApiRequest::get(Operation::SearchDeclarations, '/p', []),
            );
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertNull($exception->httpStatus());
            static::assertSame(TransportFailure::class, $exception->causeClass());
            static::assertNull($exception->getPrevious());
            static::assertTrue($exception->mayHaveReachedUrssaf());
            static::assertSame(Operation::SearchDeclarations, $exception->operation());
            static::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }
}
