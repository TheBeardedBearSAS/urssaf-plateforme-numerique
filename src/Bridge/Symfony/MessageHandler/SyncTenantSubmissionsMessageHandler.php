<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatuses;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\SyncTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Polls the URSSAF status of the in-process submissions of a tenant, under the same lock as "urssaf:pn:submissions:sync".
 */
final readonly class SyncTenantSubmissionsMessageHandler
{
    public function __construct(private SyncSubmissionStatusesHandler $sync, private OperationLocks $locks) {}

    public function __invoke(SyncTenantSubmissionsMessage $message): void
    {
        $this->locks->exclusively(
            OperationLocks::SYNC_SUBMISSIONS,
            $message->tenantId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->sync)(SyncSubmissionStatuses::forTenant(TenantId::fromString($message->tenantId), null))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
