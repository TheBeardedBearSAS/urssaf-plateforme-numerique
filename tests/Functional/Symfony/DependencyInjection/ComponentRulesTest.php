<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ConfigurationRules;

/**
 * The rules of the optional Symfony components, for both availabilities of each component.
 */
final class ComponentRulesTest extends TestCase
{
    #[DataProvider('availability')]
    public function testALockIsOnlyMissingWhenItIsRequiredAndTheComponentIsNotInstalled(bool|string $enabled, bool $installed, bool $expected): void
    {
        static::assertSame($expected, ConfigurationRules::lockRequiredButMissing(['lock' => ['enabled' => $enabled]], $installed));
    }

    /**
     * @return iterable<string, array{bool|string, bool, bool}>
     */
    public static function availability(): iterable
    {
        yield 'required, missing' => [true, false, true];
        yield 'required, installed' => [true, true, false];
        yield 'auto, missing' => ['auto', false, false];
        yield 'disabled, missing' => [false, false, false];
    }

    public function testTheSchedulerIsOnlyMissingWhenItIsEnabledAndTheComponentIsNotInstalled(): void
    {
        static::assertTrue(ConfigurationRules::schedulerRequiredButMissing(['scheduler' => ['enabled' => true]], false));
        static::assertFalse(ConfigurationRules::schedulerRequiredButMissing(['scheduler' => ['enabled' => true]], true));
        static::assertFalse(ConfigurationRules::schedulerRequiredButMissing(['scheduler' => ['enabled' => false]], false));
    }

    public function testTheInstalledComponentsAreDetected(): void
    {
        static::assertFalse(ConfigurationRules::lockEnabledWithoutComponent(['lock' => ['enabled' => true]]));
        static::assertFalse(ConfigurationRules::schedulerEnabledWithoutComponent(['scheduler' => ['enabled' => true]]));
    }
}
