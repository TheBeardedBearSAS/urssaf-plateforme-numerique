<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * A poll of a declaration observed a raw "paymentStatus" different from the previous one.
 */
final readonly class PaymentStatusChanged implements DomainEvent
{
    public function __construct(
        private SubmissionIdentity $identity,
        private UrssafReference $urssafReference,
        private ?string $previousRawPaymentStatus,
        private ?string $rawPaymentStatus,
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

    /**
     * Null when none was known before.
     */
    public function previousRawPaymentStatus(): ?string
    {
        return $this->previousRawPaymentStatus;
    }

    /**
     * Null when the poll returned none.
     */
    public function rawPaymentStatus(): ?string
    {
        return $this->rawPaymentStatus;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
