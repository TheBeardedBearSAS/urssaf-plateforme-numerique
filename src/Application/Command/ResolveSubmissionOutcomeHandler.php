<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Records the operator decision on an outcome_unknown submission in one transaction (ADR 0003): received ->
 * in_process with the given urssafRef (refused when another submission of the tenant already holds it), not received -> not_received.
 */
final readonly class ResolveSubmissionOutcomeHandler
{
    public function __construct(
        private SubmissionRepository $submissions,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws RecordNotFound         "submission.not_found"
     * @throws InvalidStateTransition when the submission is not outcome_unknown
     * @throws InvariantViolation     "submission.urssaf_reference_already_recorded" when the given reference is already held
     */
    public function __invoke(ResolveSubmissionOutcome $command): SubmissionView
    {
        return $this->unitOfWork->run(function (RecordedEvents $events) use ($command): SubmissionView {
            $submission = $this->submissions->get($command->tenantId, $command->submissionId);
            $this->resolve($submission, $command->urssafReference, Instant::fromDateTime($this->clock->now()));
            $this->submissions->save($submission);
            $events->record(...$submission->releaseEvents());

            return SubmissionView::fromSubmission($submission);
        });
    }

    /**
     * @throws InvalidStateTransition
     * @throws InvariantViolation     "submission.urssaf_reference_already_recorded"
     */
    private function resolve(Submission $submission, ?UrssafReference $reference, Instant $now): void
    {
        if ($reference instanceof UrssafReference) {
            if ($this->submissions->isUrssafReferenceKnown($submission->tenantId(), $submission->identity()->kind(), $reference)) {
                throw InvariantViolation::because(
                    'submission.urssaf_reference_already_recorded',
                    'Another submission of the tenant already holds this URSSAF reference.',
                    ['urssaf_ref' => $reference->value()],
                );
            }
            $submission->adoptReference($reference, $now);

            return;
        }
        $submission->markNotReceived($now);
    }
}
