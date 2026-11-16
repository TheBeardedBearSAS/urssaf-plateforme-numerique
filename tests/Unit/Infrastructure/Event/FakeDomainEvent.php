<?php

declare(strict_types=1);

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
