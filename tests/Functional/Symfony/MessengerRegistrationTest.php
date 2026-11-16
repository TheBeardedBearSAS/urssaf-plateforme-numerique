<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\ReconcileTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\SyncTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\ReconcileTenantSubmissionsMessageHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\SyncTenantSubmissionsMessageHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class MessengerRegistrationTest extends TestCase
{
    private const MESSENGER = ['messenger' => ['enabled' => true]];

    public function testNothingIsRegisteredWhenMessengerIsDisabled(): void
    {
        $harness = Harness::boot();

        static::assertFalse($harness->hasPrivate(SyncTenantSubmissionsMessageHandler::class));
        static::assertFalse($harness->hasPrivate(ReconcileTenantSubmissionsMessageHandler::class));
    }

    public function testTheHandlersAreRegisteredWithMessenger(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);

        static::assertTrue($harness->hasPrivate(SyncTenantSubmissionsMessageHandler::class));
        static::assertTrue($harness->hasPrivate(ReconcileTenantSubmissionsMessageHandler::class));
    }

    public function testTheHandlersAreRegisteredForTheScheduleToo(): void
    {
        $container = (new TestKernel(['scheduler' => ['enabled' => true]]))->definitions();

        foreach ([SyncTenantSubmissionsMessageHandler::class, ReconcileTenantSubmissionsMessageHandler::class] as $handler) {
            static::assertTrue($container->getDefinition($handler)->hasTag('messenger.message_handler'), $handler);
        }
    }

    public function testTheMessagesAreRoutedToTheTransport(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);
        $bus = $harness->privateService('messenger.default_bus', MessageBusInterface::class);

        $bus->dispatch(new SyncTenantSubmissionsMessage('tenant-1'));
        $bus->dispatch(new ReconcileTenantSubmissionsMessage('tenant-1'));

        $sent = $harness->privateService('messenger.transport.async', InMemoryTransport::class)->getSent();
        static::assertCount(2, $sent);
        static::assertEquals(new SyncTenantSubmissionsMessage('tenant-1'), $sent[0]->getMessage());
        static::assertEquals(new ReconcileTenantSubmissionsMessage('tenant-1'), $sent[1]->getMessage());
    }

    public function testTheSynchronisationPollsTheSubmissionsOfTheTenant(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);
        $report = Scenario::uploadedIdentification($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_GET_IDENTIFICATION, 200, HttpScript::identificationBody('REJECTED'));
        static::assertSame('in_process', self::state($harness, $report->submissionId));

        $harness->privateService(SyncTenantSubmissionsMessageHandler::class, SyncTenantSubmissionsMessageHandler::class)(new SyncTenantSubmissionsMessage(Scenario::TENANT));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_GET_IDENTIFICATION));
        static::assertSame('rejected', self::state($harness, $report->submissionId));
    }

    public function testTheReconciliationAdoptsTheReferenceFoundAtUrssaf(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);
        $harness->urssaf->failTransport(HttpScript::ROUTE_UPLOAD_IDENTIFICATION);
        $report = Scenario::uploadedIdentification($harness);
        static::assertSame('outcome_unknown', self::state($harness, $report->submissionId));

        $harness->privateService(ReconcileTenantSubmissionsMessageHandler::class, ReconcileTenantSubmissionsMessageHandler::class)(new ReconcileTenantSubmissionsMessage(Scenario::TENANT));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_SEARCH_IDENTIFICATIONS));
        static::assertSame('in_process', self::state($harness, $report->submissionId));
    }

    private static function state(Harness $harness, SubmissionId $id): string
    {
        return $harness->privateService(GetSubmissionHandler::class, GetSubmissionHandler::class)(new GetSubmission(TenantId::fromString(Scenario::TENANT), $id))->state;
    }
}
