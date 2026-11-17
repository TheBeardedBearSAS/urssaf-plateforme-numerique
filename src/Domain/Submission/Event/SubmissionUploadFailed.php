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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * The upload provably did not reach URSSAF (error) or the credentials were refused (invalid_credentials).
 */
final readonly class SubmissionUploadFailed implements DomainEvent
{
    public function __construct(
        private SubmissionIdentity $identity,
        private SubmissionState $state,
        private Instant $occurredAt,
    ) {}

    public function identity(): SubmissionIdentity
    {
        return $this->identity;
    }

    /**
     * error or invalid_credentials.
     */
    public function state(): SubmissionState
    {
        return $this->state;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
