<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;

final class EnvironmentPlaceholderTest extends TestCase
{
    public function testTheEnvironmentMayComeFromAnEnvironmentVariable(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'test_api';
        $_SERVER['URSSAF_TEST_ENVIRONMENT'] = 'test_api';
        try {
            $configuration = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%'])
                ->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT'], $_SERVER['URSSAF_TEST_ENVIRONMENT']);
        }

        static::assertSame(Environment::TestApi, $configuration->environment());
        static::assertSame('https://api.urssaf.fr/api-odspn-be-platforms-sandbox/v1/platforms', $configuration->apiUri('/platforms'));
    }
}
