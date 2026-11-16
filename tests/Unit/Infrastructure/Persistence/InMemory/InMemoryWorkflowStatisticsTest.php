<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemorySubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence\WorkflowStatisticsContract;

final class InMemoryWorkflowStatisticsTest extends TestCase
{
    use WorkflowStatisticsContract;

    protected function createAdapters(): array
    {
        $submissions = new InMemorySubmissionRepository();

        return [new InMemoryWorkflowStatistics($submissions), $submissions];
    }
}
