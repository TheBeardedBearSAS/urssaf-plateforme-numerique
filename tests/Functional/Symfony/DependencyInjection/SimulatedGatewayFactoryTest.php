<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\DependencyInjection;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\SimulatedGatewayFactory;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FixedClock;
use ValueError;

/**
 * The runtime guard of the "gateway.simulated" rule (ADR 0012).
 */
final class SimulatedGatewayFactoryTest extends TestCase
{
    public function testTheSandboxGetsTheSimulator(): void
    {
        $gateway = SimulatedGatewayFactory::create(new FixedClock('2025-06-02T08:00:00Z'), 'sandbox');

        static::assertSame([], $gateway->calls());
        static::assertSame([], $gateway->newsFeed(TenantId::fromString('tenant-1'), PlatformId::fromString('platform-1'), PageRequest::firstPage())->items());
    }

    public function testTheSimulatorOfTheTestApiHasNoNewsFeed(): void
    {
        $gateway = SimulatedGatewayFactory::create(new FixedClock('2025-06-02T08:00:00Z'), 'test_api');

        $this->expectException(OperationNotSupported::class);

        $gateway->newsFeed(TenantId::fromString('tenant-1'), PlatformId::fromString('platform-1'), PageRequest::firstPage());
    }

    public function testProductionIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"gateway.simulated" is meant for tests and cannot be used with the production environment.');

        SimulatedGatewayFactory::create(new FixedClock('2025-06-02T08:00:00Z'), 'production');
    }

    public function testAnUnknownEnvironmentIsRefused(): void
    {
        $this->expectException(ValueError::class);

        SimulatedGatewayFactory::create(new FixedClock('2025-06-02T08:00:00Z'), 'staging');
    }
}
