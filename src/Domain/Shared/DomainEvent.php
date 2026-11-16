<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared;

use DateTimeImmutable;

/**
 * Marker for every event recorded by an aggregate.
 */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
