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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\CacheKey;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;

final class OAuth2TokenCacheTest extends TestCase
{
    private function credentials(string $clientId = StaticCredentialsProvider::CLIENT_ID): Credentials
    {
        return Credentials::of($clientId, StaticCredentialsProvider::SHARED_VALUE);
    }

    private const ISSUED = 'fictitious-access-token-0001';

    public function testItCachesTheTokenUnderAPsr6SafeKeyWithoutSecrets(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $provider = $stack->tokenProvider();

        $provider->accessToken($this->credentials(), Operation::GetNewsFeed);
        $second = $provider->accessToken($this->credentials(), Operation::GetDeclaration);

        static::assertSame(self::ISSUED, $second);
        static::assertCount(1, $stack->requests());
        $expectedKey = CacheKey::of('urssaf_pn.' . hash(
            'sha256',
            'sandbox|https://api-edi.urssaf.fr|ods.pn.platforms|' . StaticCredentialsProvider::CLIENT_ID,
        ));
        static::assertSame([$expectedKey], $stack->tokens->keys());
        static::assertMatchesRegularExpression('/^[A-Za-z0-9_.]+$/', $expectedKey);
    }

    public function testTheCacheKeyDependsOnClientTargetAndBaseUri(): void
    {
        $keys = [];
        $variants = [
            [ApiConfiguration::forEnvironment(Environment::Sandbox), 'client-a'],
            [ApiConfiguration::forEnvironment(Environment::Sandbox), 'client-b'],
            [ApiConfiguration::forEnvironment(Environment::Production), 'client-a'],
            [ApiConfiguration::forEnvironment(Environment::TestApi), 'client-a'],
            [new ApiConfiguration(Environment::Sandbox, 'https://proxy.example.test', null), 'client-a'],
        ];
        foreach ($variants as [$configuration, $clientId]) {
            $stack = HttpStacks::configured($configuration);
            $stack->respondToken();
            $stack->tokenProvider()->accessToken($this->credentials($clientId), Operation::GetNewsFeed);
            $keys[] = $stack->tokens->keys()[0];
        }

        static::assertCount(5, array_unique($keys));
    }

    public function testInvalidateForcesANewToken(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetNewsFeed);

        $provider->invalidate($this->credentials());

        static::assertSame([], $stack->tokens->keys());
        static::assertSame('second-token', $provider->accessToken($this->credentials(), Operation::GetNewsFeed));
    }

    public function testInvalidateOnlyAffectsTheGivenClient(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respondToken();
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials('client-a'), Operation::GetNewsFeed);
        $provider->accessToken($this->credentials('client-b'), Operation::GetNewsFeed);

        $provider->invalidate($this->credentials('client-a'));

        static::assertCount(1, $stack->tokens->keys());
    }
}
