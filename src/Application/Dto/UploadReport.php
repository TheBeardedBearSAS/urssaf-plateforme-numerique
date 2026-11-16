<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Result of an upload command: what was done, and the record as it is after it.
 */
final readonly class UploadReport
{
    /**
     * @param list<SubmissionError> $errors the errors of the refused upload ("rejected" only)
     */
    private function __construct(
        public SubmissionId $submissionId,
        public UploadOutcome $outcome,
        public SubmissionState $state,
        public ?UrssafReference $urssafReference,
        public array $errors,
    ) {}

    public static function of(Submission $submission, UploadOutcome $outcome): self
    {
        return new self(
            $submission->id(),
            $outcome,
            $submission->state(),
            $submission->urssafReference(),
            $outcome === UploadOutcome::Rejected ? $submission->errors() : [],
        );
    }
}
