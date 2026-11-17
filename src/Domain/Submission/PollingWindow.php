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

use DateInterval;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

/**
 * How often and how long submissions are polled (ADR 0004). Package choices, not URSSAF rules:
 * - statusPollInterval: between two polls of a submission in process;
 * - paymentPollInterval: between two polls of a processed declaration whose payment is not settled;
 * - maxPollingAge: after the creation of the submission, it is not polled any more.
 * Intervals are copied on the way in and out, and must not be negative.
 * An interval elapses at its exact end; the maximum age is exceeded only strictly after it.
 */
final readonly class PollingWindow
{
    private function __construct(
        private DateInterval $statusPollInterval,
        private DateInterval $paymentPollInterval,
        private DateInterval $maxPollingAge,
    ) {}

    public static function of(
        DateInterval $statusPollInterval,
        DateInterval $paymentPollInterval,
        DateInterval $maxPollingAge,
    ): self {
        foreach ([$statusPollInterval, $paymentPollInterval, $maxPollingAge] as $interval) {
            if ($interval->invert === 1) {
                throw InvalidValue::because('polling_window.negative', 'Invalid polling_window: intervals must not be negative.');
            }
        }

        return new self(clone $statusPollInterval, clone $paymentPollInterval, clone $maxPollingAge);
    }

    /**
     * 10 minutes, 6 hours, 1 year.
     */
    public static function defaults(): self
    {
        return new self(new DateInterval('PT10M'), new DateInterval('PT6H'), new DateInterval('P1Y'));
    }

    public function statusPollInterval(): DateInterval
    {
        return clone $this->statusPollInterval;
    }

    public function paymentPollInterval(): DateInterval
    {
        return clone $this->paymentPollInterval;
    }

    public function maxPollingAge(): DateInterval
    {
        return clone $this->maxPollingAge;
    }

    /**
     * True when $now is strictly after $createdAt + maxPollingAge.
     */
    public function isExpired(Instant $createdAt, Instant $now): bool
    {
        return $now->isAfter($createdAt->plus($this->maxPollingAge));
    }

    /**
     * True when never polled, or when statusPollInterval has elapsed since the last poll.
     */
    public function isStatusPollDue(?Instant $lastPolledAt, Instant $now): bool
    {
        return self::isDue($lastPolledAt, $this->statusPollInterval, $now);
    }

    /**
     * True when never polled, or when paymentPollInterval has elapsed since the last poll.
     */
    public function isPaymentPollDue(?Instant $lastPolledAt, Instant $now): bool
    {
        return self::isDue($lastPolledAt, $this->paymentPollInterval, $now);
    }

    private static function isDue(?Instant $lastPolledAt, DateInterval $interval, Instant $now): bool
    {
        return !$lastPolledAt instanceof Instant || !$now->isBefore($lastPolledAt->plus($interval));
    }
}
