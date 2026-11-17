<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

/**
 * The last poll of a submission: when it happened, and the raw "status" and "paymentStatus" its last answer read
 * (verbatim, null while never answered or when absent; also held by the snapshot).
 *
 * The raw codes are kept apart from the snapshot so that a storage can filter on them with the very values that
 * {@see Submission::needsPolling()} reads (real columns of the Doctrine bridge, ADR 0011).
 */
final readonly class LastPoll
{
    private function __construct(
        private ?Instant $polledAt,
        private ?string $rawStatus,
        private ?string $rawPaymentStatus,
    ) {}

    public static function never(): self
    {
        return new self(null, null, null);
    }

    /**
     * Polled at $now, URSSAF answering with the snapshot.
     */
    public static function answered(SubmissionSnapshot $snapshot, Instant $now): self
    {
        return new self($now, $snapshot->rawStatus(), $snapshot->rawPaymentStatus());
    }

    /**
     * Polled again at $now without an answer: the codes of the last answer are kept.
     */
    public function unansweredAt(Instant $now): self
    {
        return new self($now, $this->rawStatus, $this->rawPaymentStatus);
    }

    /**
     * Null while never polled.
     */
    public function polledAt(): ?Instant
    {
        return $this->polledAt;
    }

    public function rawPaymentStatus(): ?string
    {
        return $this->rawPaymentStatus;
    }

    /**
     * The last answer read ACCEPTED and PROCESSED (never answered: not settled), as
     * {@see SubmissionSnapshot::isPaymentSettled()}.
     */
    public function isPaymentSettled(): bool
    {
        return FileStatus::fromCode($this->rawStatus) === FileStatus::Accepted
            && PaymentStatus::fromCode($this->rawPaymentStatus) === PaymentStatus::Processed;
    }
}
