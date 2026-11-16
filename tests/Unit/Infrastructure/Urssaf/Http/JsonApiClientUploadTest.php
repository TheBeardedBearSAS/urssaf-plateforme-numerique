<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

/**
 * An upload is never resent blindly: ADR 0003.
 */
final class JsonApiClientUploadTest extends TestCase
{
    private function call(int $status, string $body, ApiRequest $request): ApiResponse
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond($status, $body, ['Retry-After' => '7']);

        return $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            $request,
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function serverStatuses(): iterable
    {
        yield '500' => [500];
        yield '502' => [502];
        yield '503' => [503];
        yield '599' => [599];
    }

    #[DataProvider('serverStatuses')]
    public function testAnyServerErrorLeavesTheOutcomeUnknown(int $status): void
    {
        try {
            $this->call($status, '[{"code":"ERR"}]', UploadRequests::of());
            static::fail('Expected an unknown outcome.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame(Operation::UploadIdentification, $exception->operation());
            static::assertTrue($exception->mayHaveReachedUrssaf());
            static::assertStringContainsString('HTTP ' . $status, $exception->getMessage());
        }
    }

    public function testATransportErrorLeavesTheOutcomeUnknownWithoutTheTransportMessage(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout on https://api.example.test/p?x=secret'));

        try {
            $stack->jsonClient()->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                UploadRequests::of(Operation::UploadDeclaration),
            );
            static::fail('Expected an unknown outcome.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame(Operation::UploadDeclaration, $exception->operation());
            static::assertStringContainsString(TransportFailure::class, $exception->getMessage());
            static::assertStringNotContainsString('secret', $exception->getMessage());
            static::assertNull($exception->getPrevious());
        }
        static::assertCount(2, $stack->requests());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableAnswers(): iterable
    {
        yield 'empty' => [''];
        yield 'not json' => ['<html>OK</html>'];
        yield 'scalar' => ['"ok"'];
        yield 'truncated' => ['{"urssafRef":"r'];
    }

    #[DataProvider('unreadableAnswers')]
    public function testAnUnreadableSuccessLeavesTheOutcomeUnknown(string $body): void
    {
        try {
            $this->call(200, $body, UploadRequests::of());
            static::fail('Expected an unknown outcome.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame(Operation::UploadIdentification, $exception->operation());
            static::assertStringContainsString('unreadable response', $exception->getMessage());
        }
    }
}
