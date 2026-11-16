<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineSubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence\WorkflowStatisticsContract;

final class DoctrineWorkflowStatisticsTest extends TestCase
{
    use WorkflowStatisticsContract;

    protected function createAdapters(): array
    {
        $entityManager = TestDatabase::entityManager();

        return [new DoctrineWorkflowStatistics($entityManager), new DoctrineSubmissionRepository($entityManager)];
    }
}
