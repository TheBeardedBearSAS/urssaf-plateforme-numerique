<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use Closure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReconcileReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReportItem;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\ReconciliationSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * Reconciles the unresolved submissions of a tenant (ADR 0003), the oldest update first, one transaction per change:
 * - "uploading" for less than the policy staleUploadAfter -> left as it is ({@see self::IN_FLIGHT}); stale -> moved to
 *   "outcome_unknown" first, with a conditional save (stored state still "uploading"); when the record changed
 *   meanwhile (the slow upload committed) it is left alone ({@see self::SUPERSEDED}) and not searched;
 * - the candidates are searched at URSSAF ({@see ReconciliationSearch});
 * - exactly one candidate -> the reference is adopted, the submission moves to in_process ({@see self::ADOPTED});
 * - none or several -> left outcome_unknown ({@see self::UNMATCHED}, {@see self::AMBIGUOUS}), or
 *   {@see self::MANUAL_REVIEW} once outcome_unknown for the policy manualReviewAfter;
 * - search refused (400) -> left as it is ({@see self::FAILED}), the run goes on;
 * - credentials refused -> the run stops: the report so far, with this submission as {@see self::INVALID_CREDENTIALS};
 * - temporary failure ({@see RetryableException}) -> the run stops and {@see RetryableUrssafFailure} is thrown; the
 *   changes made before stay saved.
 */
final readonly class ReconcileUnknownSubmissionsHandler
{
    public const ADOPTED = 'adopted';
    public const UNMATCHED = 'unmatched';
    public const AMBIGUOUS = 'ambiguous';
    public const MANUAL_REVIEW = 'manual_review';
    public const IN_FLIGHT = 'in_flight';
    public const SUPERSEDED = 'superseded';
    public const FAILED = 'failed';
    public const INVALID_CREDENTIALS = 'invalid_credentials';

    private ReconciliationSearch $search;

    public function __construct(
        private SubmissionRepository $submissions,
        PlatformGateway $gateway,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {
        $this->search = new ReconciliationSearch($submissions, $gateway, $policy);
    }

    /**
     * @throws RetryableUrssafFailure
     */
    public function __invoke(ReconcileUnknownSubmissions $command): ReconcileReport
    {
        $now = Instant::fromDateTime($this->clock->now());
        $report = ReconcileReport::of();
        foreach ($this->submissions->unresolved($command->tenantId, $this->policy->pageSize($command->limit)) as $submission) {
            $id = $submission->id()->value();
            try {
                $outcome = $this->reconcile($submission, $now);
            } catch (UrssafRequestRejected) {
                $outcome = self::FAILED;
            } catch (UrssafAuthenticationFailed) {
                return $report->with(new ReportItem($id, self::INVALID_CREDENTIALS));
            } catch (RetryableException $failure) {
                throw RetryableUrssafFailure::causedBy($failure);
            }
            $report = $report->with(new ReportItem($id, $outcome));
        }

        return $report;
    }

    private function reconcile(Submission $submission, Instant $now): string
    {
        if ($submission->state() === SubmissionState::Uploading) {
            if (!$submission->isStaleUpload($now, $this->policy->staleUploadAfter())) {
                return self::IN_FLIGHT;
            }
            if (!$this->markStaleUploadUnknown($submission, $now)) {
                return self::SUPERSEDED;
            }
        }
        $candidates = $this->search->candidates($submission->identity());
        if (\count($candidates) === 1) {
            $this->change($submission, static function (Submission $found) use ($candidates, $now): void {
                $found->adoptReference($candidates[0], $now);
            });

            return self::ADOPTED;
        }
        if ($submission->needsManualReview($now, $this->policy->manualReviewAfter())) {
            return self::MANUAL_REVIEW;
        }

        return $candidates === [] ? self::UNMATCHED : self::AMBIGUOUS;
    }

    /**
     * Conditional save: false (nothing recorded) when the stored record is no longer "uploading".
     */
    private function markStaleUploadUnknown(Submission $stale, Instant $now): bool
    {
        return $this->unitOfWork->run(function (RecordedEvents $events) use ($stale, $now): bool {
            $stale->markOutcomeUnknown($now);
            $released = $stale->releaseEvents();
            if (!$this->submissions->claim($stale, SubmissionState::Uploading)) {
                return false;
            }
            $events->record(...$released);

            return true;
        });
    }

    /**
     * @param Closure(Submission): void $transition
     * @param-immediately-invoked-callable $transition
     */
    private function change(Submission $submission, Closure $transition): void
    {
        $this->unitOfWork->run(function (RecordedEvents $events) use ($submission, $transition): void {
            $transition($submission);
            $this->submissions->save($submission);
            $events->record(...$submission->releaseEvents());
        });
    }
}
