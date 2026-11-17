<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;

/**
 * URSSAF refused the upload (400) with the given errors.
 */
final readonly class SubmissionUploadRejected implements DomainEvent
{
    /**
     * @param list<SubmissionError> $errors
     */
    public function __construct(
        private SubmissionIdentity $identity,
        private array $errors,
        private Instant $occurredAt,
    ) {}

    public function identity(): SubmissionIdentity
    {
        return $this->identity;
    }

    /**
     * @return list<SubmissionError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
