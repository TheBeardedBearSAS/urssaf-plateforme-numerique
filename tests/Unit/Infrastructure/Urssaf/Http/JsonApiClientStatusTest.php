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
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiResponse;

/**
 * Status mapping of a read (ADR 0002).
 */
final class JsonApiClientStatusTest extends TestCase
{
    private const ERRORS = '[{"code":"ERR_A","message":"Message A","description":"Description A"},{"code":"ERR_B"}]';

    /**
     * @param array<string, string> $headers
     */
    private function call(int $status, string $body, array $headers = []): ApiResponse
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond($status, $body, $headers);
        // Only used when the status is 401: the single replay (new token, then the same status again).
        $stack->respondToken();
        $stack->respond($status, $body, $headers);

        return $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::GetIdentification, '/p', []),
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function successStatuses(): iterable
    {
        yield '200' => [200];
        yield '201' => [201];
        yield '299' => [299];
    }

    #[DataProvider('successStatuses')]
    public function testTwoHundredStatusesReturnTheDecodedBody(int $status): void
    {
        static::assertSame('v', $this->call($status, '{"k":"v"}')->payload()->string('k'));
    }

    public function testA400IsARejectionCarryingTheParsedErrors(): void
    {
        try {
            $this->call(400, self::ERRORS);
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(400, $exception->httpStatus());
            static::assertSame(Operation::GetIdentification, $exception->operation());
            static::assertSame(['ERR_A', 'ERR_B'], $exception->errors()->codes());
        }
    }

    public function testA400WithAnUnreadableBodyIsStillARejection(): void
    {
        try {
            $this->call(400, '<html>Bad Request</html>');
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertTrue($exception->errors()->isEmpty());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function authenticationStatuses(): iterable
    {
        yield 'unauthorized' => [401];
        yield 'forbidden' => [403];
    }

    #[DataProvider('authenticationStatuses')]
    public function testSecurityStatusesFailAuthentication(int $status): void
    {
        try {
            $this->call($status, '');
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame($status, $exception->httpStatus());
        }
    }

    public function testA404IsAResourceNotFoundCarryingTheParsedErrors(): void
    {
        try {
            $this->call(404, self::ERRORS);
            static::fail('Expected a not-found exception.');
        } catch (UrssafResourceNotFound $exception) {
            static::assertSame(Operation::GetIdentification, $exception->operation());
            static::assertSame(['ERR_A', 'ERR_B'], $exception->errors()->codes());
        }
    }

    /**
     * @return iterable<string, array{int, array<string, string>, int|null}>
     */
    public static function retryableStatuses(): iterable
    {
        yield 'quota with seconds' => [429, ['Retry-After' => '12'], 12];
        yield 'quota without header' => [429, [], null];
        yield 'quota with http date' => [429, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], null];
        yield 'technical error' => [500, [], null];
        yield 'bad gateway' => [502, [], null];
        yield 'unavailable with seconds' => [503, ['Retry-After' => '3'], 3];
        yield 'last server status' => [599, [], null];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('retryableStatuses')]
    public function testQuotaAndServerErrorsAreRetryable(int $status, array $headers, ?int $retryAfter): void
    {
        try {
            $this->call($status, self::ERRORS, $headers);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertSame($status, $exception->httpStatus());
            static::assertSame($retryAfter, $exception->retryAfterSeconds());
            static::assertSame($status === 429, $exception->isQuotaExceeded());
            static::assertSame(Operation::GetIdentification, $exception->operation());
            static::assertSame($status !== 429, $exception->mayHaveReachedUrssaf());
            static::assertSame(['ERR_A', 'ERR_B'], $exception->errors()->codes());
        }
    }
}
