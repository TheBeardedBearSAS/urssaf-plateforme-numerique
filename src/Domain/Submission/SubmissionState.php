<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;

/**
 * Local lifecycle of a submission (ADR 0004).
 *
 * Transitions:
 * - uploading           -> in_process | upload_rejected | error | invalid_credentials | outcome_unknown
 * - in_process          -> accepted | rejected
 * - accepted            -> rejected, rejected -> accepted (the order of the polled statuses is not enforced)
 * - outcome_unknown     -> in_process (reference found) | not_received (operator decision)
 * - upload_rejected, error, invalid_credentials, not_received -> uploading (reclaimed by a new upload)
 */
enum SubmissionState: string
{
    private const AGGREGATE = 'submission';

    /** Claimed: the upload call is in flight. */
    case Uploading = 'uploading';

    /** URSSAF returned an urssafRef: the processing status is polled. */
    case InProcess = 'in_process';

    /** Polled status ACCEPTED. */
    case Accepted = 'accepted';

    /** Polled status REJECTED. */
    case Rejected = 'rejected';

    /** The upload was refused (400): the file must be fixed and uploaded again. */
    case UploadRejected = 'upload_rejected';

    /** The call provably did not reach URSSAF: may be uploaded again. */
    case Error = 'error';

    /** URSSAF refused the tenant credentials: upload again once they are fixed. */
    case InvalidCredentials = 'invalid_credentials';

    /** The call may have reached URSSAF but no answer was received (ADR 0003). */
    case OutcomeUnknown = 'outcome_unknown';

    /** An operator decided that an outcome_unknown upload never reached URSSAF: may be uploaded again. */
    case NotReceived = 'not_received';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Uploading => [self::InProcess, self::UploadRejected, self::Error, self::InvalidCredentials, self::OutcomeUnknown],
            self::InProcess => [self::Accepted, self::Rejected],
            self::Accepted => [self::Rejected],
            self::Rejected => [self::Accepted],
            self::OutcomeUnknown => [self::InProcess, self::NotReceived],
            self::UploadRejected, self::Error, self::InvalidCredentials, self::NotReceived => [self::Uploading],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @throws InvalidStateTransition when the transition is not allowed
     */
    public function transitionTo(self $target): self
    {
        if (!$this->canTransitionTo($target)) {
            throw InvalidStateTransition::between(self::AGGREGATE, $this->value, $target->value);
        }

        return $target;
    }

    /**
     * The state after a poll returned this processing status: ACCEPTED and REJECTED are followed, any other status
     * (in process, unknown or missing) keeps the current state. The transition itself is not checked here.
     */
    public function afterPolledStatus(?FileStatus $status): self
    {
        return match ($status) {
            FileStatus::Accepted => self::Accepted,
            FileStatus::Rejected => self::Rejected,
            FileStatus::InProcess, null => $this,
        };
    }

    /**
     * The upload may be attempted again (the record is reclaimed by a new upload of the same file).
     */
    public function isReclaimable(): bool
    {
        return match ($this) {
            self::UploadRejected, self::Error, self::InvalidCredentials, self::NotReceived => true,
            self::Uploading, self::InProcess, self::Accepted, self::Rejected, self::OutcomeUnknown => false,
        };
    }

    /**
     * URSSAF holds the file (an urssafRef is known): its status can be polled.
     */
    public function isKnownToUrssaf(): bool
    {
        return match ($this) {
            self::InProcess, self::Accepted, self::Rejected => true,
            self::Uploading, self::UploadRejected, self::Error, self::InvalidCredentials, self::OutcomeUnknown, self::NotReceived => false,
        };
    }

    /**
     * The file is, or may be, in process at URSSAF: another declaration of the same type is refused (ADR 0008).
     */
    public function occupiesDeclarationSlot(): bool
    {
        return match ($this) {
            self::Uploading, self::InProcess, self::OutcomeUnknown => true,
            self::Accepted, self::Rejected, self::UploadRejected, self::Error, self::InvalidCredentials, self::NotReceived => false,
        };
    }
}
