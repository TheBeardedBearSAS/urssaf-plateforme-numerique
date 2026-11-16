<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Service;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * Polls one submission known to URSSAF: reads it on the platform it was uploaded to (ADR 0007), outside any
 * transaction, then applies the snapshot and saves the record in one transaction (the poll instant always moves).
 * Shared by the SyncSubmissionStatuses and RefreshSubmission handlers.
 */
final readonly class SubmissionPoller
{
    public function __construct(
        private SubmissionRepository $submissions,
        private PlatformGateway $gateway,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @return bool true when the poll changed the state or the payment status (events recorded)
     *
     * @throws InvalidStateTransition     when the submission is not known to URSSAF (no urssafRef)
     * @throws UrssafResourceNotFound     when URSSAF does not know the reference
     * @throws UrssafRequestRejected      when URSSAF refuses the read
     * @throws UrssafAuthenticationFailed when URSSAF refuses the credentials
     * @throws RetryableException         after a temporary failure
     */
    public function poll(Submission $submission): bool
    {
        $snapshot = $this->read($submission);

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($submission, $snapshot): bool {
            $submission->applySnapshot($snapshot, Instant::fromDateTime($this->clock->now()));
            $this->submissions->save($submission);
            $released = $submission->releaseEvents();
            $events->record(...$released);

            return $released !== [];
        });
    }

    /**
     * Records, in its own transaction, a poll URSSAF did not answer with a snapshot (404, refused read): the poll
     * instant moves so that the other submissions are polled first next time.
     *
     * @throws InvalidStateTransition when the submission is not known to URSSAF
     */
    public function recordPollWithoutAnswer(Submission $submission): void
    {
        $this->unitOfWork->run(function () use ($submission): void {
            $submission->recordPollWithoutAnswer(Instant::fromDateTime($this->clock->now()));
            $this->submissions->save($submission);
        });
    }

    private function read(Submission $submission): SubmissionSnapshot
    {
        $identity = $submission->identity();
        $reference = $submission->urssafReference()
            ?? throw InvalidStateTransition::between('submission', $submission->state()->value, SubmissionState::InProcess->value);

        return $identity->kind() === SubmissionKind::Declaration
            ? SnapshotFactory::fromDeclaration($this->gateway->getDeclaration($identity->tenantId(), $identity->platformId(), $reference))
            : SnapshotFactory::fromIdentification($this->gateway->getIdentification($identity->tenantId(), $identity->platformId(), $reference));
    }
}
