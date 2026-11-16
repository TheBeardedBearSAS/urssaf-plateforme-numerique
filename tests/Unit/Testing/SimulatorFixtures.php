<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Testing;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FixedClock;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\StreamFileSource;

/**
 * Builders shared by the simulator tests (fictitious tenant, platform and file names).
 */
final class SimulatorFixtures
{
    public const NOW = '2026-03-10T08:00:00Z';

    public static function gateway(FixedClock $clock = new FixedClock(self::NOW)): SimulatedPlatformGateway
    {
        return new SimulatedPlatformGateway($clock, Environment::Sandbox);
    }

    public static function gatewayFor(Environment $environment): SimulatedPlatformGateway
    {
        return new SimulatedPlatformGateway(new FixedClock(self::NOW), $environment);
    }

    /**
     * @return list<string> the raw withholding periods of the declaration
     */
    public static function periods(DeclarationSubmission $declaration): array
    {
        return array_map(static fn(ReportedPeriod $period): string => $period->raw(), $declaration->periods());
    }

    public static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function otherTenant(): TenantId
    {
        return TenantId::fromString('tenant-2');
    }

    public static function platform(): PlatformId
    {
        return PlatformId::fromString('platform-1');
    }

    public static function otherPlatform(): PlatformId
    {
        return PlatformId::fromString('platform-2');
    }

    public static function file(string $name): StreamFileSource
    {
        return StreamFileSource::named($name, 'fictitious content');
    }

    public static function uploadIdentification(SimulatedPlatformGateway $urssaf, string $name = 'DPID_202505_24001_000_20250428150823.xml.gz'): UrssafReference
    {
        return $urssaf->uploadIdentification(self::tenant(), self::platform(), self::file($name));
    }

    public static function uploadDeclaration(
        SimulatedPlatformGateway $urssaf,
        string $name = 'DPDE_202505_24001_000_20250428150823.xml.gz',
        DeclarationType $type = DeclarationType::Declaration,
    ): UrssafReference {
        return $urssaf->uploadDeclaration(self::tenant(), self::platform(), $type, self::file($name));
    }
}
