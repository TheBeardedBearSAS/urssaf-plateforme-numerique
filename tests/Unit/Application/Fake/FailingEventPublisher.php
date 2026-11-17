<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\EventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;

final readonly class FailingEventPublisher implements EventPublisher
{
    public function __construct(public RuntimeException $failure) {}

    public function publish(DomainEvent ...$events): void
    {
        throw $this->failure;
    }
}
