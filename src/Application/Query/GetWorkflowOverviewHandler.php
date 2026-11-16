<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\WorkflowOverview;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * The submissions by tenant and state, for dashboards and alerting.
 */
final readonly class GetWorkflowOverviewHandler
{
    public function __construct(private WorkflowStatistics $statistics) {}

    public function __invoke(GetWorkflowOverview $query): WorkflowOverview
    {
        $counts = $this->statistics->submissionStates();

        return $query->tenantId instanceof TenantId
            ? WorkflowOverview::forTenant($query->tenantId, $counts)
            : WorkflowOverview::allTenants($counts);
    }
}
