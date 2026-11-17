<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiResponse;

/**
 * Statuses of an upload that are not an unknown outcome: ADR 0002 and 0003.
 */
final class JsonApiClientUploadStatusTest extends TestCase
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
     * @return iterable<string, array{Operation}>
     */
    public static function uploads(): iterable
    {
        yield 'identification' => [Operation::UploadIdentification];
        yield 'declaration' => [Operation::UploadDeclaration];
    }

    #[DataProvider('uploads')]
    public function testASuccessReturnsTheDecodedAnswer(Operation $operation): void
    {
        $response = $this->call(200, '{"urssafRef":"r-1"}', UploadRequests::of($operation));

        static::assertSame('r-1', $response->payload()->string('urssafRef'));
    }

    public function testA429IsRetryableBecauseTheUploadDidNotReachUrssaf(): void
    {
        try {
            $this->call(429, '', UploadRequests::of());
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertTrue($exception->isQuotaExceeded());
            static::assertFalse($exception->mayHaveReachedUrssaf());
            static::assertSame(7, $exception->retryAfterSeconds());
        }
    }

    public function testA400IsARejectionWithItsErrors(): void
    {
        try {
            $this->call(400, '[{"code":"ERR_NAME","message":"m","description":"d"}]', UploadRequests::of());
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(['ERR_NAME'], $exception->errors()->codes());
        }
    }

    public function testA403IsAnAuthenticationFailure(): void
    {
        $this->expectException(UrssafAuthenticationFailed::class);

        $this->call(403, '', UploadRequests::of());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unexpectedStatuses(): iterable
    {
        yield 'redirect' => [302];
        yield 'not found' => [404];
        yield 'conflict' => [409];
        yield 'informational' => [100];
    }

    #[DataProvider('unexpectedStatuses')]
    public function testOtherStatusesAreUnexpectedAndNotProcessed(int $status): void
    {
        try {
            $this->call($status, '{}', UploadRequests::of());
            static::fail('Expected an unexpected-response exception.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame($status, $exception->getCode());
        }
    }

    public function testAFailedTokenRequestStaysRetryableAndSendsNothing(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respond(503, '');

        try {
            $stack->jsonClient()->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                UploadRequests::of(),
            );
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertFalse($exception->mayHaveReachedUrssaf());
        }
        static::assertCount(1, $stack->requests());
    }
}
