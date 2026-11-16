<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Scheduler;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\ReconcileTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\SyncTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Scheduler\UrssafScheduleProvider;

final class UrssafScheduleProviderTest extends TestCase
{
    public function testEveryTenantIsSynchronisedAndReconciledAtItsFrequency(): void
    {
        $schedule = (new UrssafScheduleProvider(['tenant-1', 'tenant-2'], 'PT10M', 'PT15M'))->getSchedule();

        static::assertSame([
            'sync tenant-1 every 600 seconds (PT10M)',
            'reconcile tenant-1 every 900 seconds (PT15M)',
            'sync tenant-2 every 600 seconds (PT10M)',
            'reconcile tenant-2 every 900 seconds (PT15M)',
        ], self::describe($schedule));
    }

    public function testATaskWithoutFrequencyIsLeftOut(): void
    {
        $withoutReconcile = (new UrssafScheduleProvider(['tenant-1'], 'PT1H', null))->getSchedule();
        $withoutSync = (new UrssafScheduleProvider(['tenant-1'], null, 'PT2H'))->getSchedule();

        static::assertSame(['sync tenant-1 every 3600 seconds (PT1H)'], self::describe($withoutReconcile));
        static::assertSame(['reconcile tenant-1 every 7200 seconds (PT2H)'], self::describe($withoutSync));
    }

    public function testThereIsNothingToScheduleWithoutTenant(): void
    {
        static::assertSame([], self::describe((new UrssafScheduleProvider([], 'PT10M', 'PT15M'))->getSchedule()));
    }

    /**
     * @return list<string> "<task> <tenant> <trigger>" of every recurring message
     */
    private static function describe(Schedule $schedule): array
    {
        $described = [];
        foreach ($schedule->getRecurringMessages() as $recurring) {
            $context = new MessageContext('urssaf_pn', $recurring->getId(), $recurring->getTrigger(), new DateTimeImmutable());
            $messages = iterator_to_array($recurring->getProvider()->getMessages($context), false);
            static::assertCount(1, $messages);
            $message = reset($messages);
            $task = match (true) {
                $message instanceof SyncTenantSubmissionsMessage => 'sync ' . $message->tenantId,
                $message instanceof ReconcileTenantSubmissionsMessage => 'reconcile ' . $message->tenantId,
                default => static::fail('Unexpected message.'),
            };
            $described[] = $task . ' ' . $recurring->getTrigger()->__toString();
        }

        return $described;
    }
}
