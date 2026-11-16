<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Lock;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\ReconcileTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\SyncTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\ReconcileTenantSubmissionsMessageHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\SyncTenantSubmissionsMessageHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\RetryDelay;

final class LockedHandlersTest extends TestCase
{
    /**
     * @param Closure(Harness): void $handle
     */
    #[DataProvider('messages')]
    public function testAHandlerAsksForARetryWhenItsOperationIsAlreadyRunning(Closure $handle, string $resource): void
    {
        $harness = Harness::boot(['messenger' => ['enabled' => true]], false, true, true);
        $held = HeldLocks::hold($harness, $resource);

        try {
            $handle($harness);
            static::fail('The handler must ask for a retry.');
        } catch (RecoverableMessageHandlingException $retry) {
            RetryDelay::assertCarries(30_000, $retry);
            static::assertSame(0, $retry->getCode());
            static::assertStringContainsString(\sprintf('lock "%s" is held', $resource), $retry->getMessage());
        }
        static::assertSame([], $harness->client->getRequests());
        static::assertTrue($held->isAcquired());
    }

    /**
     * @return iterable<string, array{Closure(Harness): void, string}>
     */
    public static function messages(): iterable
    {
        yield 'sync submissions' => [
            static function (Harness $harness): void {
                $harness->privateService(SyncTenantSubmissionsMessageHandler::class, SyncTenantSubmissionsMessageHandler::class)(new SyncTenantSubmissionsMessage('tenant-1'));
            },
            'urssaf_pn.submissions.sync.tenant-1',
        ];
        yield 'reconcile submissions' => [
            static function (Harness $harness): void {
                $harness->privateService(ReconcileTenantSubmissionsMessageHandler::class, ReconcileTenantSubmissionsMessageHandler::class)(new ReconcileTenantSubmissionsMessage('tenant-1'));
            },
            'urssaf_pn.submissions.reconcile.tenant-1',
        ];
    }

    public function testTheLockIsReleasedAfterTheRun(): void
    {
        $harness = Harness::boot(['messenger' => ['enabled' => true]], false, true, true);

        $harness->privateService(SyncTenantSubmissionsMessageHandler::class, SyncTenantSubmissionsMessageHandler::class)(new SyncTenantSubmissionsMessage('tenant-1'));

        $again = HeldLocks::hold($harness, 'urssaf_pn.submissions.sync.tenant-1');
        static::assertTrue($again->isAcquired());
    }
}
