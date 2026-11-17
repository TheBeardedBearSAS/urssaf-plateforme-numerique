<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use DateInterval;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissions;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissionsHandler as Handler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReconcileReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

/**
 * Shared steps of the reconciliation tests.
 */
final class ReconcileScenario
{
    public static function reconcile(Harness $harness, ?int $limit): ReconcileReport
    {
        return self::handler($harness)(ReconcileUnknownSubmissions::forTenant(Harness::tenant(), $limit));
    }

    public static function handler(Harness $harness): Handler
    {
        return self::handlerOver($harness, $harness->submissions);
    }

    public static function handlerOver(Harness $harness, SubmissionRepository $submissions): Handler
    {
        return new Handler($submissions, $harness->gateway, $harness->clock, $harness->unitOfWork, $harness->policy);
    }

    public static function stored(Harness $harness, string $id): Submission
    {
        return $harness->submissions->get(Harness::tenant(), SubmissionId::fromString($id));
    }

    public static function policy(int $reconciliationMaxPages): WorkflowPolicy
    {
        return new WorkflowPolicy(
            new DateInterval('PT10M'),
            new DateInterval('PT6H'),
            new DateInterval('P1Y'),
            5,
            new DateInterval('PT1H'),
            new DateInterval('P1D'),
            $reconciliationMaxPages,
        );
    }
}
