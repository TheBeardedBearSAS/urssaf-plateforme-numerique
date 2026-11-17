<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Standalone\Support;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Platform\InMemoryPlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafPlateformeNumerique\Standalone\UrssafPlateformeNumeriqueBuilder;

/**
 * The builder over a PSR-18 mock client, with one fictitious tenant.
 */
final class StandaloneSetup
{
    public const TENANT = 'tenant-1';

    public static function token(): string
    {
        return '{"access_token":"fictitious-access-token-0001","token_type":"Bearer","expires_in":3600,"scope":"ods.pn.platforms"}';
    }

    public static function builder(Client $http): UrssafPlateformeNumeriqueBuilder
    {
        $factory = new Psr17Factory();

        return UrssafPlateformeNumeriqueBuilder::create(
            new HttpTransport($http, $factory, $factory),
            new InMemoryCredentialsProvider([self::TENANT => Credentials::of('client-1', 'secret-1')]),
            new InMemoryPlatformResolver([self::TENANT => PlatformId::fromString('platform-1')]),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        );
    }
}
