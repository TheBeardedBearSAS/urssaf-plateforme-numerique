<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\EventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;

/**
 * Event port dispatching each domain event, in order, through a PSR-14 dispatcher.
 */
final readonly class Psr14EventPublisher implements EventPublisher
{
    public function __construct(private EventDispatcherInterface $dispatcher) {}

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
