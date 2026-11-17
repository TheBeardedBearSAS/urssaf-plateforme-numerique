<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic;

/**
 * A check run by `urssaf:pn:check`. Services tagged "urssaf_plateforme_numerique.installation_check" (autoconfigured)
 * are run after the checks of the package, unless their tag has a positive priority.
 */
interface InstallationCheck
{
    public const TAG = 'urssaf_plateforme_numerique.installation_check';

    /**
     * @return list<CheckResult>
     */
    public function run(CheckContext $context): array;
}
