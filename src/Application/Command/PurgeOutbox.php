<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use DateInterval;

/**
 * Deletes the outbox entries dispatched for longer than the retention.
 */
final readonly class PurgeOutbox
{
    /**
     * @param ?DateInterval $retention null: the outbox retention of the workflow policy
     */
    private function __construct(public ?DateInterval $retention) {}

    public static function of(DateInterval $retention): self
    {
        return new self($retention);
    }

    /**
     * With the outbox retention of the workflow policy (30 days by default).
     */
    public static function withPolicyRetention(): self
    {
        return new self(null);
    }
}
