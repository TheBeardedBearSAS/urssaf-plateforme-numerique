<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\EventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;

/**
 * Event port recording published events in memory, in publication order (tests and in-process inspection).
 */
final class InMemoryEventPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    /**
     * @return list<DomainEvent>
     */
    public function published(): array
    {
        return $this->events;
    }
}
