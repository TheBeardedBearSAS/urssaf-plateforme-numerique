<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Diagnostic;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Platform\InMemoryPlatformResolver;

final class CredentialsCheckTest extends TestCase
{
    public function testEveryTenantWithCredentialsAndAPlatformIsOk(): void
    {
        $check = new CredentialsCheck(
            new InMemoryCredentialsProvider(['tenant-1' => Credentials::of('client-1', 'fictitious-secret'), 'tenant-2' => Credentials::of('client-2', 'fictitious-secret')]),
            new InMemoryPlatformResolver(['tenant-1' => PlatformId::fromString('platform-1'), 'tenant-2' => PlatformId::fromString('platform-2')]),
        );

        static::assertEquals(
            [
                new CheckResult('credentials', 'tenant-1', CheckStatus::Ok, 'Credentials and platform found.'),
                new CheckResult('credentials', 'tenant-2', CheckStatus::Ok, 'Credentials and platform found.'),
            ],
            $check->run(self::context('tenant-1', 'tenant-2')),
        );
    }

    public function testATenantWithoutCredentialsIsAFailure(): void
    {
        $check = new CredentialsCheck(
            new InMemoryCredentialsProvider([]),
            new InMemoryPlatformResolver(['tenant-1' => PlatformId::fromString('platform-1')]),
        );

        $results = $check->run(self::context('tenant-1'));

        static::assertCount(1, $results);
        static::assertSame(['credentials', 'tenant-1', CheckStatus::Failure], [$results[0]->check, $results[0]->subject, $results[0]->status]);
        static::assertStringContainsString('tenant-1', $results[0]->detail);
    }

    public function testATenantWithoutPlatformIsAFailure(): void
    {
        $check = new CredentialsCheck(
            new InMemoryCredentialsProvider(['tenant-1' => Credentials::of('client-1', 'fictitious-secret')]),
            new InMemoryPlatformResolver([]),
        );

        $results = $check->run(self::context('tenant-1'));

        static::assertCount(1, $results);
        static::assertSame(CheckStatus::Failure, $results[0]->status);
        static::assertStringContainsString('platform', $results[0]->detail);
        static::assertStringNotContainsString('fictitious-secret', $results[0]->detail);
    }

    private static function context(string $first, string ...$others): CheckContext
    {
        return CheckContext::withRemoteCalls([TenantId::fromString($first), ...array_values(array_map(TenantId::fromString(...), $others))]);
    }
}
