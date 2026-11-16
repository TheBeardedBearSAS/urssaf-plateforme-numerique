<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Diagnostic;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

final class CheckContextTest extends TestCase
{
    public function testTheContextTellsWhetherUrssafMayBeCalled(): void
    {
        $tenants = [TenantId::fromString('tenant-1'), TenantId::fromString('tenant-2')];

        $remote = CheckContext::withRemoteCalls($tenants);
        $local = CheckContext::withoutRemoteCalls($tenants);

        static::assertTrue($remote->remoteCallsAllowed);
        static::assertFalse($local->remoteCallsAllowed);
        static::assertSame($tenants, $remote->tenants);
        static::assertSame($tenants, $local->tenants);
    }
}
