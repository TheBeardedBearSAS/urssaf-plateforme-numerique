<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;

final readonly class GetSubmissionHandler
{
    public function __construct(private SubmissionRepository $submissions) {}

    /**
     * @throws RecordNotFound "submission.not_found" (also for a submission of another tenant)
     */
    public function __invoke(GetSubmission $query): SubmissionView
    {
        return SubmissionView::fromSubmission($this->submissions->get($query->tenantId, $query->submissionId));
    }
}
