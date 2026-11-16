<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory;

use Override;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\StateCount;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;

/**
 * Counts the submissions of the in-memory repository.
 */
final readonly class InMemoryWorkflowStatistics implements WorkflowStatistics
{
    public function __construct(private InMemorySubmissionRepository $submissions) {}

    #[Override]
    public function submissionStates(): array
    {
        $tenants = [];
        $counts = [];
        foreach ($this->submissions->all() as $submission) {
            $tenantId = $submission->tenantId();
            $tenants[$tenantId->value()] = $tenantId;
            $state = $submission->state()->value;
            $counts[$tenantId->value()][$state] = ($counts[$tenantId->value()][$state] ?? 0) + 1;
        }

        $stateCounts = [];
        foreach ($counts as $tenant => $byState) {
            foreach ($byState as $state => $count) {
                $stateCounts[] = new StateCount($tenants[$tenant], $state, $count);
            }
        }

        return $stateCounts;
    }
}
