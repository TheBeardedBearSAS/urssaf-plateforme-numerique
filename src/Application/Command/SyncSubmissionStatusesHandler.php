<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReportItem;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SyncReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\SubmissionPoller;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

/**
 * Polls, least recently polled first, the submissions of a tenant due for polling (policy polling window, ADR 0004),
 * each one read then applied in its own transaction:
 * - read -> snapshot applied ({@see self::CHANGED} when the state or the payment status changed, {@see self::UNCHANGED}
 *   otherwise);
 * - unknown to URSSAF (404) -> only the poll instant is recorded ({@see self::NOT_FOUND}), so
 *   that the other submissions are polled first next time;
 * - read refused (400) -> only the poll instant is recorded ({@see self::FAILED}), the run
 *   goes on;
 * - credentials refused -> the run stops: the report so far, with this submission as {@see self::INVALID_CREDENTIALS};
 * - temporary failure ({@see RetryableException}) -> the run stops and {@see RetryableUrssafFailure} is thrown; the
 *   submissions polled before stay saved.
 */
final readonly class SyncSubmissionStatusesHandler
{
    public const CHANGED = 'changed';
    public const UNCHANGED = 'unchanged';
    public const NOT_FOUND = 'not_found';
    public const FAILED = 'failed';
    public const INVALID_CREDENTIALS = 'invalid_credentials';

    private SubmissionPoller $poller;

    public function __construct(
        private SubmissionRepository $submissions,
        PlatformGateway $gateway,
        private Clock $clock,
        UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {
        $this->poller = new SubmissionPoller($submissions, $gateway, $clock, $unitOfWork);
    }

    /**
     * @throws RetryableUrssafFailure
     */
    public function __invoke(SyncSubmissionStatuses $command): SyncReport
    {
        $due = $this->submissions->dueForPolling(
            $command->tenantId,
            Instant::fromDateTime($this->clock->now()),
            $this->policy->pollingWindow(),
            $this->policy->pageSize($command->limit),
        );
        $report = SyncReport::of();
        foreach ($due as $submission) {
            $id = $submission->id()->value();
            try {
                $outcome = $this->poller->poll($submission) ? self::CHANGED : self::UNCHANGED;
            } catch (UrssafResourceNotFound) {
                $this->poller->recordPollWithoutAnswer($submission);
                $outcome = self::NOT_FOUND;
            } catch (UrssafRequestRejected) {
                $this->poller->recordPollWithoutAnswer($submission);
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
}
