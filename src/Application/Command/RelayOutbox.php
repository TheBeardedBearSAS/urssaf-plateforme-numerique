<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

/**
 * Delivers the pending integration events of the outbox, in recording order.
 */
final readonly class RelayOutbox
{
    private function __construct(public ?int $limit) {}

    /**
     * @param ?int $limit events read at most (null: the default page size of the workflow policy, capped by its maximum)
     */
    public static function of(?int $limit): self
    {
        return new self($limit);
    }
}
