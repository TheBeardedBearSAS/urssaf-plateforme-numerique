<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared;

use DateTimeImmutable;

/**
 * Marker for every event recorded by an aggregate.
 */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
