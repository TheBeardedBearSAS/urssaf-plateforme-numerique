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
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

final class OAuth2TokenProviderFailureTest extends TestCase
{
    private function fetch(HttpStack $stack): string
    {
        return $stack->tokenProvider()->accessToken(
            Credentials::of(StaticCredentialsProvider::CLIENT_ID, StaticCredentialsProvider::SHARED_VALUE),
            Operation::UploadDeclaration,
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function authenticationStatuses(): iterable
    {
        yield 'bad request' => [400];
        yield 'unauthorized' => [401];
        yield 'forbidden' => [403];
    }

    #[DataProvider('authenticationStatuses')]
    public function testBadCredentialsFailAuthentication(int $status): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respond($status, '{"error":"invalid_client"}');

        try {
            $this->fetch($stack);
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame($status, $exception->httpStatus());
        }
        static::assertSame([], $stack->tokens->keys());
    }

    /**
     * @return iterable<string, array{int, array<string, string>, int|null}>
     */
    public static function unavailableStatuses(): iterable
    {
        yield 'quota with Retry-After' => [429, ['Retry-After' => '17'], 17];
        yield 'quota without Retry-After' => [429, [], null];
        yield 'server error' => [500, [], null];
        yield 'bad gateway' => [502, [], null];
        yield 'service unavailable' => [503, ['Retry-After' => '5'], 5];
        yield 'security gateway' => [512, [], null];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('unavailableStatuses')]
    public function testQuotaAndServerErrorsAreRetryable(int $status, array $headers, ?int $retryAfter): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respond($status, '', $headers);

        try {
            $this->fetch($stack);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertNull($exception->causeClass());
            static::assertTrue($exception->errors()->isEmpty());
            static::assertSame($status, $exception->httpStatus());
            static::assertSame($retryAfter, $exception->retryAfterSeconds());
            static::assertSame(Operation::UploadDeclaration, $exception->operation());
            static::assertFalse($exception->mayHaveReachedUrssaf());
        }
    }

    public function testTransportErrorsAreRetryableAndKeepTheCause(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->fail(new TransportFailure('connection refused'));

        try {
            $this->fetch($stack);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertNull($exception->httpStatus());
            static::assertSame(TransportFailure::class, $exception->causeClass());
            static::assertNull($exception->getPrevious());
            static::assertSame(Operation::UploadDeclaration, $exception->operation());
            static::assertFalse($exception->mayHaveReachedUrssaf());
        }
    }

    public function testTheErrorsOfAnUnavailableAnswerAreKept(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respond(503, '[{"code":"ERR_X","message":"m","description":"d"}]');

        try {
            $this->fetch($stack);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertSame(['ERR_X'], $exception->errors()->codes());
        }
    }
}
