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

use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Outbox;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

final readonly class PurgeOutboxHandler
{
    public function __construct(private Outbox $outbox, private Clock $clock, private WorkflowPolicy $policy) {}

    /**
     * @return int the number of deleted entries
     */
    public function __invoke(PurgeOutbox $command): int
    {
        $retention = $command->retention ?? $this->policy->outboxRetention();

        return $this->outbox->purgeDispatchedBefore(Instant::fromDateTime($this->clock->now())->minus($retention));
    }
}
