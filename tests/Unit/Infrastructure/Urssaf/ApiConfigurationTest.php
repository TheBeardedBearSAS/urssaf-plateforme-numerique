<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;

final class ApiConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{Environment, string, string, string}>
     */
    public static function environments(): iterable
    {
        yield 'production' => [
            Environment::Production,
            'https://api.urssaf.fr',
            'https://api.urssaf.fr/api-odspn-be-platforms/v1/x',
            'ods.pn.platforms',
        ];
        yield 'sandbox' => [
            Environment::Sandbox,
            'https://api-edi.urssaf.fr',
            'https://api-edi.urssaf.fr/api-odspn-be-platforms/v1/x',
            'ods.pn.platforms',
        ];
        yield 'test api' => [
            Environment::TestApi,
            'https://api.urssaf.fr',
            'https://api.urssaf.fr/api-odspn-be-platforms-sandbox/v1/x',
            'ods.pn.platforms.sandbox',
        ];
    }

    #[DataProvider('environments')]
    public function testItResolvesTheTargetHostApiUriAndScope(
        Environment $environment,
        string $baseUri,
        string $apiUri,
        string $scope,
    ): void {
        $configuration = ApiConfiguration::forEnvironment($environment);

        static::assertSame($baseUri, $configuration->baseUri());
        static::assertSame($apiUri, $configuration->apiUri('/x'));
        static::assertSame($scope, $configuration->scope());
        static::assertSame($environment, $configuration->environment());
    }

    public function testItBuildsTheTokenUriOnTheHost(): void
    {
        static::assertSame(
            'https://api-edi.urssaf.fr/api/oauth/v1/token',
            ApiConfiguration::forEnvironment(Environment::Sandbox)->tokenUri(),
        );
    }

    public function testTheHostOverrideAppliesToEveryUriButNotToThePathAndTheScope(): void
    {
        $configuration = new ApiConfiguration(Environment::TestApi, 'https://proxy.example.test/', null);

        static::assertSame('https://proxy.example.test', $configuration->baseUri());
        static::assertSame('https://proxy.example.test/api/oauth/v1/token', $configuration->tokenUri());
        static::assertSame('https://proxy.example.test/api-odspn-be-platforms-sandbox/v1/x', $configuration->apiUri('/x'));
        static::assertSame('ods.pn.platforms.sandbox', $configuration->scope());
    }

    public function testItHasSensibleDefaults(): void
    {
        $configuration = ApiConfiguration::forEnvironment(Environment::Sandbox);

        static::assertNull($configuration->requestIdHeader());
        static::assertSame('FR', $configuration->acceptLanguage());
        static::assertSame(60, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('thebeardedbear-urssaf-plateforme-numerique', $configuration->userAgent());
        static::assertSame('Europe/Paris', $configuration->apiTimeZone()->getName());
    }

    public function testItExposesCustomSettings(): void
    {
        $timeZone = new DateTimeZone('UTC');

        $configuration = new ApiConfiguration(Environment::Sandbox, null, 'X-Request-Id', 'EN', 5, 'my-agent', $timeZone);

        static::assertSame('X-Request-Id', $configuration->requestIdHeader());
        static::assertSame('EN', $configuration->acceptLanguage());
        static::assertSame(5, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('my-agent', $configuration->userAgent());
        static::assertSame($timeZone, $configuration->apiTimeZone());
    }
}
