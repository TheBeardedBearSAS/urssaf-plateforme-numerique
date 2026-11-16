<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake;

use DateTimeImmutable;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;

/**
 * Clock stopped on a given instant, moved by hand.
 */
final class AdjustableClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->now = $this->now->setTimestamp($this->now->getTimestamp() + $seconds);
    }
}
