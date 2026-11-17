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

use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\InstallationCheck;

/**
 * A check that returns the given results and remembers the context it ran with.
 */
final class RecordingCheck implements InstallationCheck
{
    private ?CheckContext $context = null;

    /**
     * @param list<CheckResult> $results
     */
    public function __construct(private readonly array $results) {}

    public static function ok(string $check): self
    {
        return new self([new CheckResult($check, '', CheckStatus::Ok, 'Fine.')]);
    }

    public function context(): ?CheckContext
    {
        return $this->context;
    }

    /**
     * @return list<CheckResult>
     */
    public function run(CheckContext $context): array
    {
        $this->context = $context;

        return $this->results;
    }
}
