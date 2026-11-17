<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\StateCount;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * One GROUP BY query (index "tenant_id, state, ..." of the submissions table).
 */
final readonly class DoctrineWorkflowStatistics implements WorkflowStatistics
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Override]
    public function submissionStates(): array
    {
        /** @var list<array{tenantId: TenantId, state: SubmissionState, total: int|string}> $rows */
        $rows = $this->entityManager
            ->createQuery(\sprintf('SELECT s.identity.tenantId AS tenantId, s.state AS state, COUNT(s.id) AS total FROM %s s GROUP BY s.identity.tenantId, s.state', Submission::class))
            ->getResult();

        return array_map(
            static fn(array $row): StateCount => new StateCount($row['tenantId'], $row['state']->value, (int) $row['total']),
            $rows,
        );
    }
}
