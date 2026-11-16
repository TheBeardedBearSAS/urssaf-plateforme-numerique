<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * A poll observed the status REJECTED.
 */
final readonly class SubmissionRejected implements DomainEvent
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
