<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Number of records of one tenant in one submission state.
 */
final readonly class StateCount
{
    /**
     * @param string $state the value of a SubmissionState
     */
    public function __construct(public TenantId $tenantId, public string $state, public int $count) {}
}
