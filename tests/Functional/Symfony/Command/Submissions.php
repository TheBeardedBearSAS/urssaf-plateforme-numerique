<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

/**
 * Submissions in the state the polling commands work on.
 */
final class Submissions
{
    private function __construct() {}

    /**
     * An identification uploaded and in process, whose status poll interval (10 minutes) has elapsed.
     */
    public static function due(Harness $harness, string $tenant = Scenario::TENANT, string $reference = Scenario::EXTERNAL_REFERENCE): string
    {
        $id = Scenario::uploadedIdentification($harness, $tenant, $reference)->submissionId->value();
        $harness->clock->sleep(700);

        return $id;
    }
}
