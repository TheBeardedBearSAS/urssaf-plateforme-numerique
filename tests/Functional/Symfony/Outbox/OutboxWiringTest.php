<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Outbox;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Outbox;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\RelayOutboxCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event\Psr14IntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;

final class OutboxWiringTest extends TestCase
{
    public function testThereIsNoOutboxByDefault(): void
    {
        $container = (new TestKernel())->definitions();

        foreach ([Outbox::class, IntegrationEventSink::class, RelayOutboxHandler::class, PurgeOutboxHandler::class, RelayOutboxCommand::class, PurgeOutboxCommand::class] as $id) {
            static::assertFalse($container->has($id), $id);
        }
    }

    public function testTheMessengerSinkUsesTheMessengerBus(): void
    {
        $container = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'messenger'], 'messenger' => ['bus' => 'my.bus']]))->definitions();

        $sink = $container->getDefinition(IntegrationEventSink::class);
        static::assertSame(MessengerIntegrationEventSink::class, $sink->getClass());
        static::assertEquals([new Reference('my.bus')], $sink->getArguments());
        static::assertSame(InMemoryOutbox::class, $container->getDefinition(Outbox::class)->getClass());
        foreach ([RelayOutboxCommand::class, PurgeOutboxCommand::class] as $command) {
            static::assertTrue($container->getDefinition($command)->isAutowired(), $command);
            static::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }
    }

    public function testTheMessengerSinkUsesTheDefaultBus(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true]], false, true);

        static::assertInstanceOf(MessengerIntegrationEventSink::class, $harness->privateService(IntegrationEventSink::class, IntegrationEventSink::class));
    }

    public function testTheEventsAreRelayedToTheEventDispatcher(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]);
        $relayed = [];
        $harness->privateService('event_dispatcher', EventDispatcherInterface::class)->addListener(
            SubmissionUploaded::class,
            static function (SubmissionUploaded $event) use (&$relayed): void {
                $relayed[] = $event;
            },
        );
        static::assertInstanceOf(Psr14IntegrationEventSink::class, $harness->privateService(IntegrationEventSink::class, IntegrationEventSink::class));

        Scenario::uploadedIdentification($harness);
        $report = $harness->get(RelayOutboxHandler::class)(RelayOutbox::of(null));

        static::assertSame(1, $report->dispatched);
        static::assertCount(1, $relayed);
        static::assertTrue($harness->has(PurgeOutboxHandler::class));
    }

    public function testTheHostProvidesTheSinkAndTheOutbox(): void
    {
        $harness = Harness::boot([
            'outbox' => ['enabled' => true, 'sink' => 'custom', 'service' => 'test.sink'],
            'persistence' => ['driver' => 'custom', 'repository' => 'test.submissions', 'transaction_manager' => 'test.transactions', 'outbox' => 'test.outbox'],
        ]);

        static::assertSame($harness->privateService('test.sink', IntegrationEventSink::class), $harness->privateService(IntegrationEventSink::class, IntegrationEventSink::class));
        static::assertSame($harness->privateService('test.outbox', Outbox::class), $harness->privateService(Outbox::class, Outbox::class));

        Scenario::uploadedIdentification($harness);
        $harness->get(RelayOutboxHandler::class)(RelayOutbox::of(null));

        static::assertCount(1, $harness->privateService('test.sink', RecordingIntegrationEventSink::class)->deliveredIds());
    }

    public function testTheOutboxServiceAlsoReplacesTheOutboxOfTheDriver(): void
    {
        $container = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher'], 'persistence' => ['outbox' => 'test.outbox']]))->definitions();

        static::assertSame('test.outbox', (string) $container->getAlias(Outbox::class));
        static::assertEquals([new Reference('event_dispatcher')], $container->getDefinition(IntegrationEventSink::class)->getArguments());
    }
}
