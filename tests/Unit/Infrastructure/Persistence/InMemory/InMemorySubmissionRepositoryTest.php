<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemorySubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence\SubmissionRepositoryContract;

/**
 * Proves the contract itself: the in-memory repository satisfies the scenarios the Doctrine one must satisfy.
 */
final class InMemorySubmissionRepositoryTest extends TestCase
{
    use SubmissionRepositoryContract;

    protected function createRepository(): SubmissionRepository
    {
        return new InMemorySubmissionRepository();
    }
}
