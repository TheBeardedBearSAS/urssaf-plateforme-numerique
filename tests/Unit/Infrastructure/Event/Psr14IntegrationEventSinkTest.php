<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Event;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionNotReceived;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event\Psr14IntegrationEventSink;

final class Psr14IntegrationEventSinkTest extends TestCase
{
    public function testDispatchesTheEventThroughTheDispatcher(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $event = self::event();

        (new Psr14IntegrationEventSink($dispatcher))->dispatch($event);

        static::assertSame([$event], $dispatcher->dispatched());
    }

    public function testAListenerFailureIsNotSwallowed(): void
    {
        $failure = new RuntimeException('listener failed');
        $sink = new Psr14IntegrationEventSink(new ThrowingEventDispatcher($failure));

        $this->expectExceptionObject($failure);

        $sink->dispatch(self::event());
    }

    private static function event(): SubmissionNotReceived
    {
        return new SubmissionNotReceived(
            'event-1',
            '2026-03-01T10:00:00Z',
            'tenant-1',
            'submission-1',
            'host-1',
            'identification',
            null,
            'file-1.zip',
            null,
        );
    }
}
