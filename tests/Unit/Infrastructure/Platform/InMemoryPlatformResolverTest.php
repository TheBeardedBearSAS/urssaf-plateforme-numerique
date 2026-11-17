<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Platform;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Platform\InMemoryPlatformResolver;

final class InMemoryPlatformResolverTest extends TestCase
{
    public function testReturnsThePlatformOfTheTenant(): void
    {
        $platform = PlatformId::fromString('platform-a');
        $resolver = new InMemoryPlatformResolver([
            'tenant-a' => $platform,
            'tenant-b' => PlatformId::fromString('platform-b'),
        ]);

        static::assertSame($platform, $resolver->forTenant(TenantId::fromString('tenant-a')));
    }

    public function testTwoTenantsMayShareAPlatform(): void
    {
        $platform = PlatformId::fromString('platform-a');
        $resolver = new InMemoryPlatformResolver(['tenant-a' => $platform, 'tenant-b' => $platform]);

        static::assertSame($platform, $resolver->forTenant(TenantId::fromString('tenant-b')));
    }

    public function testUnknownTenantIsRecordNotFound(): void
    {
        $resolver = new InMemoryPlatformResolver(['tenant-a' => PlatformId::fromString('platform-a')]);

        try {
            $resolver->forTenant(TenantId::fromString('tenant-z'));
            static::fail('The tenant has no platform.');
        } catch (RecordNotFound $exception) {
            static::assertSame('platform.not_found', $exception->violationCode());
            static::assertSame('tenant-z', $exception->identifier());
        }
    }
}
