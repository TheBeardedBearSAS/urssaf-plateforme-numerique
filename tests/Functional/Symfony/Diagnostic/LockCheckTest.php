<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Diagnostic;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\LockCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

final class LockCheckTest extends TestCase
{
    public function testAnEnabledLockIsOk(): void
    {
        $check = new LockCheck(new OperationLocks(new LockFactory(new InMemoryStore()), 60.0));

        static::assertEquals(
            [new CheckResult('lock', '', CheckStatus::Ok, 'Enabled: use a store shared by every host that runs the crons and workers.')],
            $check->run(self::context()),
        );
    }

    public function testADisabledLockIsAWarning(): void
    {
        $check = new LockCheck(new OperationLocks(null, 60.0));

        static::assertEquals(
            [new CheckResult('lock', '', CheckStatus::Warning, 'Disabled: overlapping runs are only protected by the claims (install symfony/lock, see docs/symfony.md).')],
            $check->run(self::context()),
        );
    }

    private static function context(): CheckContext
    {
        return CheckContext::withoutRemoteCalls([TenantId::fromString('tenant-1')]);
    }
}
