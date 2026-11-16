<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Logging;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Event\FakeDomainEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

final class PsrLoggerFailureReporterTest extends TestCase
{
    public function testItLogsTheFailureAndTheEventClassesAtErrorLevel(): void
    {
        $logger = new CapturingLogger();
        $at = new DateTimeImmutable('2026-01-01T10:00:00+00:00');

        (new PsrLoggerFailureReporter($logger))->eventPublicationFailed(
            new RuntimeException('listener exploded'),
            [new FakeDomainEvent($at), new FakeDomainEvent($at)],
        );

        static::assertSame([[
            'level' => 'error',
            'message' => 'Domain event publication failed after commit.',
            'context' => [
                'exception' => RuntimeException::class,
                'message' => 'listener exploded',
                'events' => [FakeDomainEvent::class, FakeDomainEvent::class],
            ],
        ]], $logger->records());
    }

    public function testItNeverLogsTheEventPayload(): void
    {
        $logger = new CapturingLogger();

        (new PsrLoggerFailureReporter($logger))->eventPublicationFailed(
            new LogicException('boom'),
            [new FakeDomainEvent(new DateTimeImmutable('2026-01-01T10:00:00+00:00'), 'payload-marker')],
        );

        static::assertStringNotContainsString('payload-marker', $logger->dump());
    }

    public function testItWorksWithoutLogger(): void
    {
        (new PsrLoggerFailureReporter())->eventPublicationFailed(new LogicException('boom'), []);

        $this->expectNotToPerformAssertions();
    }
}
