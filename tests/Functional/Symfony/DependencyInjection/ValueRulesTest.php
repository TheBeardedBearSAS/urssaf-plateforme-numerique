<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ValueRules;

final class ValueRulesTest extends TestCase
{
    public function testAnEnvironmentPlaceholderIsAcceptedWhileTheContainerIsValidated(): void
    {
        static::assertFalse(ValueRules::isUnknownEnvironment(''));
        static::assertFalse(ValueRules::isUnknownEnvironment('production'));
        static::assertFalse(ValueRules::isUnknownEnvironment('sandbox'));
        static::assertFalse(ValueRules::isUnknownEnvironment('test_api'));
        static::assertTrue(ValueRules::isUnknownEnvironment('staging'));
        static::assertTrue(ValueRules::isUnknownEnvironment(null));
    }

    public function testTimeZonesAndDurations(): void
    {
        static::assertFalse(ValueRules::isUnknownTimeZone('Europe/Paris'));
        static::assertTrue(ValueRules::isUnknownTimeZone('Mars/Phobos'));
        static::assertTrue(ValueRules::isUnknownTimeZone(null));
        static::assertFalse(ValueRules::isInvalidInterval('PT15M'));
        static::assertTrue(ValueRules::isInvalidInterval('soon'));
        static::assertTrue(ValueRules::isInvalidInterval(15));
        static::assertFalse(ValueRules::isInvalidOptionalInterval(null));
        static::assertFalse(ValueRules::isInvalidOptionalInterval('P1D'));
        static::assertTrue(ValueRules::isInvalidOptionalInterval('often'));
    }

    public function testAStaleUploadDelayMustBeAValidDurationLongerThanZero(): void
    {
        static::assertFalse(ValueRules::isInvalidPositiveInterval('PT1S'));
        static::assertFalse(ValueRules::isInvalidPositiveInterval('P1D'));
        static::assertTrue(ValueRules::isInvalidPositiveInterval('PT0S'));
        static::assertTrue(ValueRules::isInvalidPositiveInterval('P0D'));
        static::assertTrue(ValueRules::isInvalidPositiveInterval('soon'));
        static::assertTrue(ValueRules::isInvalidPositiveInterval(3600));
    }
}
