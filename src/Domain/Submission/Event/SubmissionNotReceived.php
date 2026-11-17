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

/**
 * An operator decided that an outcome_unknown upload never reached URSSAF.
 */
final readonly class SubmissionNotReceived implements DomainEvent
{
    public function __construct(
        private SubmissionIdentity $identity,
        private Instant $occurredAt,
    ) {}

    public function identity(): SubmissionIdentity
    {
        return $this->identity;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
