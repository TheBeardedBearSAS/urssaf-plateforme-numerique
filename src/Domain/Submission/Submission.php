<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use DateInterval;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\PaymentStatusChanged;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionAccepted;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionNotReceived;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionOutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploadFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploadRejected;

/**
 * Aggregate root: an identification or declaration file of a tenant, from its upload to its final URSSAF status
 * (ADR 0004).
 *
 * Lifecycle: see {@see SubmissionState}. The record is created in "uploading" when the upload is claimed; the outcome of
 * the call is then recorded ({@see self::markUploaded()}, {@see self::markUploadRejected()}, {@see self::markFailed()},
 * {@see self::markInvalidCredentials()} or {@see self::markOutcomeUnknown()}). Once URSSAF holds the file, every poll
 * is applied by {@see self::applySnapshot()}.
 *
 * An outcome_unknown upload is never sent again automatically: URSSAF may hold it (ADR 0003). It is resolved by
 * {@see self::adoptReference()} when found at URSSAF, or by {@see self::markNotReceived()} (operator decision).
 *
 * Not final only because Doctrine generates lazy-loading proxies by extending the mapped entities: final by
 * convention, never extend it. For the same reason the identifier is neither readonly nor private: a proxy is created
 * with its identifier, which Doctrine assigns again (another instance of the same value) when the proxy is loaded. It is
 * never reassigned otherwise.
 *
 * The root exposes the whole state read by the persistence and wire mappers, hence the expected method count.
 *
 * @final
 *
 * @mago-expect lint:too-many-methods
 */
class Submission
{
    private const AGGREGATE = 'submission';

    protected SubmissionId $id;

    private SubmissionState $state = SubmissionState::Uploading;

    private int $attempts = 1;

    private ?UrssafReference $urssafReference = null;

    /** @var list<SubmissionError> */
    private array $errors = [];

    private ?SubmissionSnapshot $snapshot = null;

    private Instant $updatedAt;

    private ?Instant $lastPolledAt = null;

    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        private readonly SubmissionIdentity $identity,
        private readonly Instant $createdAt,
    ) {
        $this->id = $identity->id();
        $this->updatedAt = $createdAt;
    }

    /**
     * Records a claimed upload: uploading, first attempt. No event: the outcome of the upload records one.
     */
    public static function start(SubmissionIdentity $identity, Instant $now): self
    {
        return new self($identity, $now);
    }

    /**
     * uploading -> in_process: URSSAF returned an urssafRef. Records {@see SubmissionUploaded}.
     *
     * @throws InvalidStateTransition
     */
    public function markUploaded(UrssafReference $reference, Instant $now): void
    {
        $this->assertState(SubmissionState::Uploading, SubmissionState::InProcess);
        $this->acknowledge($reference, $now);
    }

    /**
     * uploading -> upload_rejected: URSSAF refused the file (400). Records {@see SubmissionUploadRejected}.
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function markUploadRejected(array $errors, Instant $now): void
    {
        $this->moveTo(SubmissionState::UploadRejected, $now);
        $this->errors = $errors;
        $this->events[] = new SubmissionUploadRejected($this->identity, $errors, $now);
    }

    /**
     * uploading -> error: the call provably did not reach URSSAF. Records {@see SubmissionUploadFailed}.
     *
     * @throws InvalidStateTransition
     */
    public function markFailed(Instant $now): void
    {
        $this->fail(SubmissionState::Error, $now);
    }

    /**
     * uploading -> invalid_credentials: URSSAF refused the tenant credentials. Records {@see SubmissionUploadFailed}.
     *
     * @throws InvalidStateTransition
     */
    public function markInvalidCredentials(Instant $now): void
    {
        $this->fail(SubmissionState::InvalidCredentials, $now);
    }

    /**
     * uploading -> outcome_unknown: the call may have reached URSSAF (ADR 0003). Records {@see SubmissionOutcomeUnknown}.
     *
     * @throws InvalidStateTransition
     */
    public function markOutcomeUnknown(Instant $now): void
    {
        $this->moveTo(SubmissionState::OutcomeUnknown, $now);
        $this->events[] = new SubmissionOutcomeUnknown($this->identity, $now);
    }

    /**
     * upload_rejected | error | invalid_credentials | not_received -> uploading: a new upload of the same file, one
     * more attempt. The errors of the previous attempt are cleared.
     *
     * @param positive-int $maxAttempts
     *
     * @throws InvalidStateTransition when the state is not reclaimable
     * @throws InvariantViolation     "submission.attempts_exhausted" once maxAttempts attempts were made
     */
    public function reclaim(Instant $now, int $maxAttempts): void
    {
        $target = $this->state->transitionTo(SubmissionState::Uploading);
        if ($this->attempts >= $maxAttempts) {
            throw InvariantViolation::because(
                'submission.attempts_exhausted',
                \sprintf('The submission was uploaded %d times: it is not uploaded again automatically.', $this->attempts),
                ['attempts' => $this->attempts, 'max_attempts' => $maxAttempts],
            );
        }
        $this->state = $target;
        ++$this->attempts;
        $this->errors = [];
        $this->updatedAt = $now;
    }

    /**
     * True when the state is reclaimable and fewer than maxAttempts attempts were made.
     *
     * @param positive-int $maxAttempts
     */
    public function canReclaim(int $maxAttempts): bool
    {
        return $this->state->isReclaimable() && $this->attempts < $maxAttempts;
    }

    /**
     * outcome_unknown -> in_process: the upload was found at URSSAF (reconciliation or operator decision).
     * Records {@see SubmissionUploaded}.
     *
     * @throws InvalidStateTransition
     */
    public function adoptReference(UrssafReference $reference, Instant $now): void
    {
        $this->assertState(SubmissionState::OutcomeUnknown, SubmissionState::InProcess);
        $this->acknowledge($reference, $now);
    }

    /**
     * outcome_unknown -> not_received: an operator decided that the upload never reached URSSAF.
     * Records {@see SubmissionNotReceived}.
     *
     * @throws InvalidStateTransition
     */
    public function markNotReceived(Instant $now): void
    {
        $this->moveTo(SubmissionState::NotReceived, $now);
        $this->events[] = new SubmissionNotReceived($this->identity, $now);
    }

    /**
     * Applies a poll to a submission known to URSSAF: records the poll instant and stores the snapshot. The status
     * ACCEPTED or REJECTED moves the state (records {@see SubmissionAccepted} or {@see SubmissionRejected} when it
     * changes); any other status keeps it. A change of the raw paymentStatus of a declaration records
     * {@see PaymentStatusChanged}. The update instant moves only on a state or payment change.
     *
     * @throws InvalidStateTransition when the submission is not known to URSSAF (no urssafRef)
     */
    public function applySnapshot(SubmissionSnapshot $snapshot, Instant $now): void
    {
        $reference = $this->knownReference();
        $previousRawPaymentStatus = $this->snapshot?->rawPaymentStatus();
        $target = $this->state->afterPolledStatus($snapshot->status());
        $this->snapshot = $snapshot;
        $this->lastPolledAt = $now;
        if ($target !== $this->state) {
            $this->state = $this->state->transitionTo($target);
            $this->updatedAt = $now;
            $this->events[] = $target === SubmissionState::Accepted
                ? new SubmissionAccepted($this->identity, $reference, $snapshot, $now)
                : new SubmissionRejected($this->identity, $reference, $snapshot, $now);
        }
        if ($this->isDeclaration() && $previousRawPaymentStatus !== $snapshot->rawPaymentStatus()) {
            $this->updatedAt = $now;
            $this->events[] = new PaymentStatusChanged(
                $this->identity,
                $reference,
                $previousRawPaymentStatus,
                $snapshot->rawPaymentStatus(),
                $now,
            );
        }
    }

    /**
     * Records a poll URSSAF did not answer with a snapshot (unknown reference, read refused): only the poll instant
     * moves, so that the other submissions are polled first next time. No event; the state, the snapshot and the update
     * instant are kept.
     *
     * @throws InvalidStateTransition when the submission is not known to URSSAF
     */
    public function recordPollWithoutAnswer(Instant $now): void
    {
        $this->knownReference();
        $this->lastPolledAt = $now;
    }

    /**
     * Known to URSSAF, created at most maxPollingAge ago, and its status may still change: in process (not polled
     * for statusPollInterval), or a processed declaration whose payment is not settled (not polled for
     * paymentPollInterval). A processed identification is never polled again.
     */
    public function needsPolling(Instant $now, PollingWindow $window): bool
    {
        if (!$this->state->isKnownToUrssaf() || $window->isExpired($this->createdAt, $now) || $this->isPaymentSettled()) {
            return false;
        }
        if ($this->state === SubmissionState::InProcess) {
            return $window->isStatusPollDue($this->lastPolledAt, $now);
        }

        return $this->isDeclaration() && $window->isPaymentPollDue($this->lastPolledAt, $now);
    }

    /**
     * Uploading since at least $after (last update + $after at or before now): the process may have died.
     */
    public function isStaleUpload(Instant $now, DateInterval $after): bool
    {
        return $this->isIdleIn(SubmissionState::Uploading, $now, $after);
    }

    /**
     * Outcome unknown since at least $after (last update + $after at or before now): an operator should decide.
     */
    public function needsManualReview(Instant $now, DateInterval $after): bool
    {
        return $this->isIdleIn(SubmissionState::OutcomeUnknown, $now, $after);
    }

    public function matchesFingerprint(ContentFingerprint $fingerprint): bool
    {
        return $this->identity->fingerprint()->equals($fingerprint);
    }

    /**
     * @return list<DomainEvent> the events recorded since the last call, oldest first
     */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function identity(): SubmissionIdentity
    {
        return $this->identity;
    }

    public function id(): SubmissionId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->identity->tenantId();
    }

    /**
     * The reference of the record in the host application.
     */
    public function externalReference(): ExternalReference
    {
        return $this->identity->externalReference();
    }

    public function state(): SubmissionState
    {
        return $this->state;
    }

    /**
     * Number of upload attempts so far (1 at creation).
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * The urssafRef, known once URSSAF holds the file.
     */
    public function urssafReference(): ?UrssafReference
    {
        return $this->urssafReference;
    }

    /**
     * Errors of a refused upload (upload_rejected); empty otherwise.
     *
     * @return list<SubmissionError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * What the last poll read; null while never polled.
     */
    public function snapshot(): ?SubmissionSnapshot
    {
        return $this->snapshot;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    public function lastPolledAt(): ?Instant
    {
        return $this->lastPolledAt;
    }

    /**
     * An urssafRef is known exactly in the states known to URSSAF (in_process, accepted, rejected).
     *
     * @throws InvalidStateTransition when the submission is not known to URSSAF
     */
    private function knownReference(): UrssafReference
    {
        return $this->urssafReference
            ?? throw InvalidStateTransition::between(self::AGGREGATE, $this->state->value, SubmissionState::InProcess->value);
    }

    /**
     * @throws InvalidStateTransition
     */
    private function moveTo(SubmissionState $target, Instant $now): void
    {
        $this->state = $this->state->transitionTo($target);
        $this->updatedAt = $now;
    }

    /**
     * The target is also reachable from another state: the transition is allowed from $expected only.
     *
     * @throws InvalidStateTransition
     */
    private function assertState(SubmissionState $expected, SubmissionState $target): void
    {
        if ($this->state !== $expected) {
            throw InvalidStateTransition::between(self::AGGREGATE, $this->state->value, $target->value);
        }
    }

    /**
     * @throws InvalidStateTransition
     */
    private function acknowledge(UrssafReference $reference, Instant $now): void
    {
        $this->moveTo(SubmissionState::InProcess, $now);
        $this->urssafReference = $reference;
        $this->events[] = new SubmissionUploaded($this->identity, $reference, $now);
    }

    /**
     * @throws InvalidStateTransition
     */
    private function fail(SubmissionState $target, Instant $now): void
    {
        $this->moveTo($target, $now);
        $this->events[] = new SubmissionUploadFailed($this->identity, $target, $now);
    }

    /**
     * The last poll read ACCEPTED and PROCESSED (never polled: not settled).
     */
    private function isPaymentSettled(): bool
    {
        return $this->snapshot instanceof SubmissionSnapshot && $this->snapshot->isPaymentSettled();
    }

    private function isDeclaration(): bool
    {
        return $this->identity->kind() === SubmissionKind::Declaration;
    }

    /**
     * In $state, not updated for at least $after (last update + $after at or before now).
     */
    private function isIdleIn(SubmissionState $state, Instant $now, DateInterval $after): bool
    {
        return $this->state === $state && !$now->isBefore($this->updatedAt->plus($after));
    }
}
