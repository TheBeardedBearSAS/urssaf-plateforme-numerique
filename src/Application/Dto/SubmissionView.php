<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;

/**
 * Read model of a local submission (primitives only; instants ISO 8601 UTC): its identity, state, attempts, what is
 * known at URSSAF ({@see UrssafTrackingView}) and the errors of a refused upload and its creation / last update instants ({@see SubmissionTimestamps}). Neither the content nor the uploader (ADR
 * 0009) are part of it.
 */
final readonly class SubmissionView
{
    /**
     * @param ?string                                      $declarationType null for an identification
     * @param list<array{code: ?string, message: ?string}> $errors
     */
    private function __construct(
        public string $submissionId,
        public string $tenantId,
        public string $platformId,
        public string $externalReference,
        public string $kind,
        public ?string $declarationType,
        public string $fileName,
        public string $state,
        public int $attempts,
        public UrssafTrackingView $urssaf,
        public array $errors,
        public SubmissionTimestamps $timestamps,
    ) {}

    public static function fromSubmission(Submission $submission): self
    {
        $identity = $submission->identity();

        return new self(
            $identity->id()->value(),
            $identity->tenantId()->value(),
            $identity->platformId()->value(),
            $identity->externalReference()->value(),
            $identity->kind()->value,
            $identity->declarationType()?->value,
            $identity->fileName()->value(),
            $submission->state()->value,
            $submission->attempts(),
            UrssafTrackingView::fromSubmission($submission),
            array_map(
                static fn(SubmissionError $error): array => ['code' => $error->code(), 'message' => $error->message()],
                $submission->errors(),
            ),
            SubmissionTimestamps::fromSubmission($submission),
        );
    }
}
