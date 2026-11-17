<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;

/**
 * Shared set-up of the live contract tests (opt-in, never part of coverage or mutation gates): every test is skipped
 * with an explicit message when the environment variables it needs are missing (see {@see SandboxGateway}).
 */
final class ContractEnvironment
{
    public static function sandbox(): SandboxGateway
    {
        $sandbox = SandboxGateway::sandboxFromEnvironment();
        if (!$sandbox instanceof SandboxGateway) {
            TestCase::markTestSkipped(
                'URSSAF sandbox contract tests are opt-in: set URSSAF_SANDBOX_CLIENT_ID and URSSAF_SANDBOX_CLIENT_SECRET (see `make test-contract`).',
            );
        }

        return $sandbox;
    }

    public static function testApi(): SandboxGateway
    {
        $testApi = SandboxGateway::testApiFromEnvironment();
        if (!$testApi instanceof SandboxGateway) {
            TestCase::markTestSkipped(
                'URSSAF test API contract tests are opt-in: set URSSAF_TEST_API_CLIENT_ID and URSSAF_TEST_API_CLIENT_SECRET (see `make test-contract`).',
            );
        }

        return $testApi;
    }

    public static function platform(SandboxGateway $target): PlatformId
    {
        if (!$target->platformId instanceof PlatformId) {
            TestCase::markTestSkipped(
                'Set URSSAF_SANDBOX_PLATFORM_ID (or URSSAF_TEST_API_PLATFORM_ID for the test API) to run the tests that need a platform.',
            );
        }

        return $target->platformId;
    }
}
