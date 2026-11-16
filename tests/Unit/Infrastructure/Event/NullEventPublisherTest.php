<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Event;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event\NullEventPublisher;

final class NullEventPublisherTest extends TestCase
{
    public function testDiscardsEventsWithoutFailing(): void
    {
        $publisher = new NullEventPublisher();

        $publisher->publish(new FakeDomainEvent(new DateTimeImmutable('2026-01-01T10:00:00+00:00')));
        $publisher->publish();

        $this->expectNotToPerformAssertions();
    }
}
