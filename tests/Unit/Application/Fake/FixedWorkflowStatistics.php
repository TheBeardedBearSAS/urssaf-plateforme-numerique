<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\StateCount;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;

final readonly class FixedWorkflowStatistics implements WorkflowStatistics
{
    /**
     * @param list<StateCount> $submissions
     */
    public function __construct(private array $submissions) {}

    public function submissionStates(): array
    {
        return $this->submissions;
    }
}
