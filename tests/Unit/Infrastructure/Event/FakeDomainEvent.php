<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;

final readonly class FakeDomainEvent implements DomainEvent
{
    public function __construct(
        private DateTimeImmutable $occurredAt,
        private string $payload = '',
    ) {}

    public function payload(): string
    {
        return $this->payload;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
