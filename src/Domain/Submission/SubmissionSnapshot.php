<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

/**
 * What the last poll read from URSSAF about a submission (ADR 0004): raw "status" and "paymentStatus", periods, report
 * file references, counters and "lastUpdateDate". Never the uploader (ADR 0009).
 */
final readonly class SubmissionSnapshot
{
    /**
     * Public constructor (more than five parameters, as the reference does for its wide value objects).
     *
     * @param list<ReportedPeriod>         $periods
     * @param array<string, DecimalNumber> $counters keyed by their wire names (e.g. "numberOfError")
     */
    public function __construct(
        private ?string $rawStatus,
        private ?string $rawPaymentStatus,
        private array $periods,
        private ReportFiles $reportFiles,
        private array $counters,
        private ?Instant $lastUpdateDate,
    ) {}

    /**
     * The "status" of the API, verbatim (null when absent).
     */
    public function rawStatus(): ?string
    {
        return $this->rawStatus;
    }

    /**
     * Null for a missing or unknown status.
     */
    public function status(): ?FileStatus
    {
        return FileStatus::fromCode($this->rawStatus);
    }

    /**
     * The "paymentStatus" of the API, verbatim (null when absent, always for identifications).
     */
    public function rawPaymentStatus(): ?string
    {
        return $this->rawPaymentStatus;
    }

    /**
     * Null for a missing or unknown payment status.
     */
    public function paymentStatus(): ?PaymentStatus
    {
        return PaymentStatus::fromCode($this->rawPaymentStatus);
    }

    /**
     * @return list<ReportedPeriod>
     */
    public function periods(): array
    {
        return $this->periods;
    }

    public function reportFiles(): ReportFiles
    {
        return $this->reportFiles;
    }

    /**
     * @return array<string, DecimalNumber> keyed by their wire names
     */
    public function counters(): array
    {
        return $this->counters;
    }

    public function lastUpdateDate(): ?Instant
    {
        return $this->lastUpdateDate;
    }

    /**
     * ACCEPTED and PROCESSED: the full flow is complete, nothing can change any more (§3.2).
     */
    public function isPaymentSettled(): bool
    {
        return $this->status() === FileStatus::Accepted && $this->paymentStatus() === PaymentStatus::Processed;
    }
}
