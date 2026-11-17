<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;

/**
 * When a local submission was created and last changed (ISO 8601 UTC).
 */
final readonly class SubmissionTimestamps
{
    private function __construct(
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromSubmission(Submission $submission): self
    {
        return new self($submission->createdAt()->format(), $submission->updatedAt()->format());
    }
}
