<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\StateCount;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;

/**
 * Behaviour shared by every {@see WorkflowStatistics} adapter.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 */
trait WorkflowStatisticsContract
{
    private const AT = '2026-03-01T10:00:00Z';

    /**
     * @return array{WorkflowStatistics, SubmissionRepository} the statistics over the repository
     */
    abstract protected function createAdapters(): array;

    public function testNothingIsCountedWithoutRecords(): void
    {
        [$statistics] = $this->createAdapters();

        static::assertSame([], $statistics->submissionStates());
    }

    public function testTheSubmissionsAreCountedByTenantAndState(): void
    {
        [$statistics, $submissions] = $this->createAdapters();
        $submissions->add(SubmissionBuilder::declaration('s1', 'tenant-1', 'ref-1', self::AT));
        $submissions->add(SubmissionBuilder::declaration('s2', 'tenant-1', 'ref-2', self::AT));
        $submissions->add(SubmissionBuilder::inProcess(SubmissionBuilder::declaration('s3', 'tenant-1', 'ref-3', self::AT), 'u-3', self::AT));
        $submissions->add(SubmissionBuilder::identification('s4', 'tenant-2', 'ref-1', self::AT));

        static::assertSame(
            ['tenant-1/in_process=1', 'tenant-1/uploading=2', 'tenant-2/uploading=1'],
            self::normalized($statistics->submissionStates()),
        );
    }

    /**
     * @param list<StateCount> $counts
     *
     * @return list<string> "tenant/state=count", sorted (the adapters do not guarantee an order)
     */
    private static function normalized(array $counts): array
    {
        $normalized = array_map(
            static fn(StateCount $count): string => \sprintf('%s/%s=%d', $count->tenantId->value(), $count->state, $count->count),
            $counts,
        );
        sort($normalized);

        return $normalized;
    }
}
