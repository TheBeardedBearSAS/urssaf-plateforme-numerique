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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * A poll observed the status ACCEPTED.
 */
final readonly class SubmissionAccepted implements DomainEvent
{
    public function __construct(
        private SubmissionIdentity $identity,
        private UrssafReference $urssafReference,
        private SubmissionSnapshot $snapshot,
        private Instant $occurredAt,
    ) {}

    public function identity(): SubmissionIdentity
    {
        return $this->identity;
    }

    public function urssafReference(): UrssafReference
    {
        return $this->urssafReference;
    }

    public function snapshot(): SubmissionSnapshot
    {
        return $this->snapshot;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
