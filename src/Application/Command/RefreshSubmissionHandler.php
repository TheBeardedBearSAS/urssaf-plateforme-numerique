<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\SubmissionPoller;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;

/**
 * Reads one submission at URSSAF and applies the snapshot in one transaction (any state known to URSSAF: in_process,
 * accepted, rejected), then returns the record as stored. The failures of the read propagate; a temporary one
 * ({@see RetryableException}) as {@see RetryableUrssafFailure}.
 */
final readonly class RefreshSubmissionHandler
{
    private SubmissionPoller $poller;

    public function __construct(
        private SubmissionRepository $submissions,
        PlatformGateway $gateway,
        Clock $clock,
        UnitOfWork $unitOfWork,
    ) {
        $this->poller = new SubmissionPoller($submissions, $gateway, $clock, $unitOfWork);
    }

    /**
     * @throws RecordNotFound             "submission.not_found"
     * @throws InvalidStateTransition     when the submission is not known to URSSAF (no urssafRef)
     * @throws UrssafResourceNotFound     when URSSAF does not know the reference
     * @throws UrssafRequestRejected      when URSSAF refuses the read
     * @throws UrssafAuthenticationFailed when URSSAF refuses the credentials
     * @throws RetryableUrssafFailure     after a temporary failure
     */
    public function __invoke(RefreshSubmission $command): SubmissionView
    {
        $submission = $this->submissions->get($command->tenantId, $command->submissionId);
        try {
            $this->poller->poll($submission);
        } catch (RetryableException $failure) {
            throw RetryableUrssafFailure::causedBy($failure);
        }

        return SubmissionView::fromSubmission($this->submissions->get($command->tenantId, $command->submissionId));
    }
}
