<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\EventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;

/**
 * Event port discarding every event (no event bus configured).
 */
final class NullEventPublisher implements EventPublisher
{
    public function publish(DomainEvent ...$events): void {}
}
