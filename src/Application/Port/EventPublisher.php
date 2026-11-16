<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;

interface EventPublisher
{
    /**
     * Publishes the events in the given order. Called by handlers after the transaction is committed.
     */
    public function publish(DomainEvent ...$events): void;
}
