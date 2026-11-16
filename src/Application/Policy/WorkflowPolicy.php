<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Policy;

use DateInterval;
use DateTimeImmutable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PollingWindow;

/**
 * Configuration shared by the use case handlers (provided by the framework bridge). Every default is a choice of this
 * package, not a rule of URSSAF (ADR 0003, 0004 and 0008); all of them can be configured:
 *
 * - statusPollInterval / paymentPollInterval / maxPollingAge: how often and how long the submissions are polled
 *   (see {@see PollingWindow}; 10 minutes, 6 hours and 1 year by default);
 * - maxUploadAttempts: upload attempts of one submission, after which it is not uploaded again automatically (5);
 * - staleUploadAfter: how long a submission may stay "uploading" before it is considered stale and moved to
 *   "outcome_unknown" (1 hour);
 * - manualReviewAfter: how long a submission may stay "outcome_unknown" before it is reported as needing a manual
 *   review (1 day);
 * - reconciliationMaxPages: pages of {@see PageRequest::MAX_SIZE} items searched to reconcile one submission (1);
 * - concurrentDeclarationGuard: refuse a declaration while another one of the same type is in process (enabled, ADR
 *   0008);
 * - outboxRetention: how long a dispatched outbox entry is kept before it is purged (30 days);
 * - defaultPageSize / maxPageSize: number of records loaded by a batch use case when no / a larger limit is asked (100
 *   and 1000).
 *
 * Intervals are copied on the way in and out, and must not be negative (staleUploadAfter must be greater than zero).
 */
final readonly class WorkflowPolicy
{
    public const DEFAULT_MAX_UPLOAD_ATTEMPTS = 5;
    public const DEFAULT_RECONCILIATION_MAX_PAGES = 1;
    public const DEFAULT_PAGE_SIZE = 100;
    public const MAX_PAGE_SIZE = 1000;

    private PollingWindow $pollingWindow;

    private DateInterval $staleUploadAfter;

    private DateInterval $manualReviewAfter;

    private DateInterval $outboxRetention;

    /** @var positive-int */
    private int $maxUploadAttempts;

    /** @var positive-int */
    private int $reconciliationMaxPages;

    /** @var positive-int */
    private int $defaultPageSize;

    /** @var positive-int */
    private int $maxPageSize;

    /**
     * Eleven settings, hence the number of parameters.
     *
     * @throws InvalidValue "workflow_policy.negative_interval", "workflow_policy.<setting>.not_positive" (including "workflow_policy.stale_upload_after.not_positive") or
     *                      "workflow_policy.default_page_size_above_max"
     */
    public function __construct(
        DateInterval $statusPollInterval = new DateInterval('PT10M'),
        DateInterval $paymentPollInterval = new DateInterval('PT6H'),
        DateInterval $maxPollingAge = new DateInterval('P1Y'),
        int $maxUploadAttempts = self::DEFAULT_MAX_UPLOAD_ATTEMPTS,
        DateInterval $staleUploadAfter = new DateInterval('PT1H'),
        DateInterval $manualReviewAfter = new DateInterval('P1D'),
        int $reconciliationMaxPages = self::DEFAULT_RECONCILIATION_MAX_PAGES,
        private bool $concurrentDeclarationGuard = true,
        DateInterval $outboxRetention = new DateInterval('P30D'),
        int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
        int $maxPageSize = self::MAX_PAGE_SIZE,
    ) {
        $this->pollingWindow = PollingWindow::of($statusPollInterval, $paymentPollInterval, $maxPollingAge);
        $this->maxUploadAttempts = self::positive($maxUploadAttempts, 'workflow_policy.max_upload_attempts');
        $this->staleUploadAfter = self::interval($staleUploadAfter, 'stale_upload_after');
        if (self::isZero($this->staleUploadAfter)) {
            throw InvalidValue::notPositive('workflow_policy.stale_upload_after');
        }
        $this->manualReviewAfter = self::interval($manualReviewAfter, 'manual_review_after');
        $this->reconciliationMaxPages = self::positive($reconciliationMaxPages, 'workflow_policy.reconciliation_max_pages');
        $this->outboxRetention = self::interval($outboxRetention, 'outbox_retention');
        $this->defaultPageSize = self::positive($defaultPageSize, 'workflow_policy.default_page_size');
        $this->maxPageSize = self::positive($maxPageSize, 'workflow_policy.max_page_size');
        if ($this->maxPageSize < $this->defaultPageSize) {
            throw InvalidValue::because(
                'workflow_policy.default_page_size_above_max',
                'Invalid workflow_policy: the default page size must not be greater than the maximum page size.',
                ['default_page_size' => $defaultPageSize, 'max_page_size' => $maxPageSize],
            );
        }
    }

    /**
     * The same policy without the guard that refuses a declaration while another of the same type is in process
     * (ADR 0008), for hosts that track the processing elsewhere or when the local store is incomplete.
     */
    public function withoutConcurrentDeclarationGuard(): self
    {
        return new self(
            $this->pollingWindow->statusPollInterval(),
            $this->pollingWindow->paymentPollInterval(),
            $this->pollingWindow->maxPollingAge(),
            $this->maxUploadAttempts,
            $this->staleUploadAfter,
            $this->manualReviewAfter,
            $this->reconciliationMaxPages,
            false,
            $this->outboxRetention,
            $this->defaultPageSize,
            $this->maxPageSize,
        );
    }

    /**
     * The status poll interval, the payment poll interval and the maximum polling age (ADR 0004).
     */
    public function pollingWindow(): PollingWindow
    {
        return $this->pollingWindow;
    }

    /**
     * Upload attempts after which a submission is not uploaded again automatically (the upload report is
     * "exhausted").
     *
     * @return positive-int
     */
    public function maxUploadAttempts(): int
    {
        return $this->maxUploadAttempts;
    }

    /**
     * A submission "uploading" for at least this long is stale: the process may have died (ADR 0003).
     */
    public function staleUploadAfter(): DateInterval
    {
        return clone $this->staleUploadAfter;
    }

    /**
     * A submission "outcome_unknown" for at least this long needs a manual review (ADR 0003).
     */
    public function manualReviewAfter(): DateInterval
    {
        return clone $this->manualReviewAfter;
    }

    /**
     * Pages of {@see self::reconciliationPageSize()} items searched to reconcile one submission (ADR 0003).
     *
     * @return positive-int
     */
    public function reconciliationMaxPages(): int
    {
        return $this->reconciliationMaxPages;
    }

    /**
     * @return positive-int
     */
    public function reconciliationPageSize(): int
    {
        return PageRequest::MAX_SIZE;
    }

    /**
     * Whether a declaration is refused while another one of the same type is in process (ADR 0008).
     */
    public function concurrentDeclarationGuard(): bool
    {
        return $this->concurrentDeclarationGuard;
    }

    /**
     * How long a dispatched outbox entry is kept before it is purged.
     */
    public function outboxRetention(): DateInterval
    {
        return clone $this->outboxRetention;
    }

    /**
     * Number of records to load: the requested limit (default page size when null), capped by the maximum page size.
     *
     * @return positive-int
     *
     * @throws InvalidValue "workflow_policy.limit.not_positive" for a limit lower than 1
     */
    public function pageSize(?int $requestedLimit): int
    {
        $limit = self::positive($requestedLimit ?? $this->defaultPageSize, 'workflow_policy.limit');

        return min($limit, $this->maxPageSize);
    }

    /**
     * @return positive-int
     */
    private static function positive(int $value, string $subject): int
    {
        if ($value < 1) {
            throw InvalidValue::notPositive($subject);
        }

        return $value;
    }

    private static function isZero(DateInterval $interval): bool
    {
        $now = new DateTimeImmutable('2000-01-01T00:00:00Z');

        return $now->add($interval)->getTimestamp() === $now->getTimestamp() && $interval->f === 0.0;
    }

    private static function interval(DateInterval $interval, string $setting): DateInterval
    {
        if ($interval->invert === 1) {
            throw InvalidValue::because(
                'workflow_policy.negative_interval',
                'Invalid workflow_policy: intervals must not be negative.',
                ['setting' => $setting],
            );
        }

        return clone $interval;
    }
}
