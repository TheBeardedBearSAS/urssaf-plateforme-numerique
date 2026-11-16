<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

final readonly class FindSubmissionsHandler
{
    public function __construct(private SubmissionRepository $submissions, private WorkflowPolicy $policy) {}

    /**
     * @return list<SubmissionView> the most recent first
     *
     * @throws InvalidValue "workflow_policy.limit.not_positive" or "offset.negative"
     */
    public function __invoke(FindSubmissions $query): array
    {
        $limit = $this->policy->pageSize($query->limit);
        if ($query->offset < 0) {
            throw InvalidValue::negative('offset');
        }

        return array_map(
            SubmissionView::fromSubmission(...),
            $this->submissions->inState($query->tenantId, $query->state, $limit, $query->offset),
        );
    }
}
