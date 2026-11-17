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

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SyncReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;

/**
 * Runs {@see SyncSubmissionStatusesHandler} for each tenant and merges the reports. A tenant whose credentials are
 * refused ends its own run only; a temporary failure stops the whole run ({@see RetryableUrssafFailure}).
 */
final readonly class SyncAllSubmissionStatusesHandler
{
    public function __construct(private SyncSubmissionStatusesHandler $syncTenant) {}

    /**
     * @throws RetryableUrssafFailure
     */
    public function __invoke(SyncAllSubmissionStatuses $command): SyncReport
    {
        $report = SyncReport::of();
        foreach ($command->tenantIds as $tenantId) {
            $report = $report->merge(($this->syncTenant)(SyncSubmissionStatuses::forTenant($tenantId, $command->limitPerTenant)));
        }

        return $report;
    }
}
