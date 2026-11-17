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

use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissions;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissionsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\ReconcileTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Reconciles the unresolved submissions of a tenant, under the same lock as "urssaf:pn:submissions:reconcile".
 */
final readonly class ReconcileTenantSubmissionsMessageHandler
{
    public function __construct(private ReconcileUnknownSubmissionsHandler $reconcile, private OperationLocks $locks) {}

    public function __invoke(ReconcileTenantSubmissionsMessage $message): void
    {
        $this->locks->exclusively(
            OperationLocks::RECONCILE_SUBMISSIONS,
            $message->tenantId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->reconcile)(ReconcileUnknownSubmissions::forTenant(TenantId::fromString($message->tenantId), null))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
