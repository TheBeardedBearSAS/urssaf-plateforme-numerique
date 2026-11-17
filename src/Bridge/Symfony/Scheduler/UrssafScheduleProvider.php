<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Scheduler;

use Override;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\ReconcileTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\SyncTenantSubmissionsMessage;

/**
 * The crons of the package as a Symfony Scheduler schedule: every configured tenant is synchronised and reconciled at
 * the configured frequencies, through the Messenger messages (same locks, same retry semantics as the workers).
 */
final readonly class UrssafScheduleProvider implements ScheduleProviderInterface
{
    /**
     * @param list<string> $tenants
     * @param ?string      $syncFrequency      ISO 8601 duration between two synchronisations, null to leave the task out
     * @param ?string      $reconcileFrequency ISO 8601 duration between two reconciliations, null to leave the task out
     */
    public function __construct(private array $tenants, private ?string $syncFrequency, private ?string $reconcileFrequency) {}

    #[Override]
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        foreach ($this->tenants as $tenant) {
            if ($this->syncFrequency !== null) {
                $schedule->add(RecurringMessage::every($this->syncFrequency, new SyncTenantSubmissionsMessage($tenant)));
            }
            if ($this->reconcileFrequency !== null) {
                $schedule->add(RecurringMessage::every($this->reconcileFrequency, new ReconcileTenantSubmissionsMessage($tenant)));
            }
        }

        return $schedule;
    }
}
