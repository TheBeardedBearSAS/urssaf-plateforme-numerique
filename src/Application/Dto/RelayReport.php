<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

/**
 * Outcome of one outbox relay run.
 */
final readonly class RelayReport
{
    /**
     * @param int $dispatched events delivered and marked as dispatched
     * @param int $failed     events whose delivery failed (kept pending)
     * @param int $deferred   events left pending because an earlier event of the same record failed (order kept)
     */
    public function __construct(public int $dispatched, public int $failed, public int $deferred) {}
}
