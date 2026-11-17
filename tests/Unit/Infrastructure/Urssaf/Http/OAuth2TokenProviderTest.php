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
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;

final class OAuth2TokenProviderTest extends TestCase
{
    private const ISSUED = 'fictitious-access-token-0001';

    private function credentials(string $clientId = StaticCredentialsProvider::CLIENT_ID): Credentials
    {
        return Credentials::of($clientId, StaticCredentialsProvider::SHARED_VALUE);
    }

    public function testItRequestsATokenWithTheClientCredentialsGrant(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();

        $token = $stack->tokenProvider()->accessToken($this->credentials(), Operation::GetNewsFeed);

        static::assertSame(self::ISSUED, $token);
        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', (string) $request->getUri());
        static::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        static::assertSame('application/json', $request->getHeaderLine('Accept'));
        static::assertSame(ApiConfiguration::DEFAULT_USER_AGENT, $request->getHeaderLine('User-Agent'));
        $form = [];
        parse_str((string) $request->getBody(), $form);
        static::assertSame(
            [
                'grant_type' => 'client_credentials',
                'client_id' => StaticCredentialsProvider::CLIENT_ID,
                'client_secret' => StaticCredentialsProvider::SHARED_VALUE,
                'scope' => 'ods.pn.platforms',
            ],
            $form,
        );
    }

    public function testItAsksTheScopeOfTheTestApiOnItsHost(): void
    {
        $stack = HttpStacks::configured(ApiConfiguration::forEnvironment(Environment::TestApi));
        $stack->respondToken();

        $stack->tokenProvider()->accessToken($this->credentials(), Operation::GetNewsFeed);

        $request = $stack->lastRequest();
        static::assertSame('https://api.urssaf.fr/api/oauth/v1/token', (string) $request->getUri());
        $form = [];
        parse_str((string) $request->getBody(), $form);
        static::assertSame('ods.pn.platforms.sandbox', $form['scope']);
    }

    public function testItUsesTheProductionHostWhenConfigured(): void
    {
        $stack = HttpStacks::configured(ApiConfiguration::forEnvironment(Environment::Production));
        $stack->respondToken();

        $stack->tokenProvider()->accessToken($this->credentials(), Operation::GetNewsFeed);

        static::assertSame('https://api.urssaf.fr/api/oauth/v1/token', (string) $stack->lastRequest()->getUri());
    }

    public function testItRefreshesAnExpiredToken(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetNewsFeed);

        $stack->clock->advanceSeconds(3600);
        $token = $provider->accessToken($this->credentials(), Operation::GetNewsFeed);

        static::assertSame('second-token', $token);
        static::assertCount(2, $stack->requests());
    }

    public function testItRefreshesTheTokenWithinTheSafetyMargin(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetNewsFeed);

        $stack->clock->advanceSeconds(3540);

        static::assertSame('second-token', $provider->accessToken($this->credentials(), Operation::GetNewsFeed));
    }

    public function testItKeepsTheTokenJustBeforeTheSafetyMargin(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetNewsFeed);

        $stack->clock->advanceSeconds(3539);

        static::assertSame(self::ISSUED, $provider->accessToken($this->credentials(), Operation::GetNewsFeed));
        static::assertCount(1, $stack->requests());
    }

    public function testTheSafetyMarginIsConfigurable(): void
    {
        $stack = HttpStacks::configured(new ApiConfiguration(Environment::Sandbox, null, null, 'FR', 600));
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetNewsFeed);

        $stack->clock->advanceSeconds(3000);

        static::assertSame('second-token', $provider->accessToken($this->credentials(), Operation::GetNewsFeed));
    }
}
