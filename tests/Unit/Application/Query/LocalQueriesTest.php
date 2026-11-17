<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Query;

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\FindSubmissions;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\FindSubmissionsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

final class LocalQueriesTest extends TestCase
{
    public function testGetsALocalSubmission(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::Uploading);
        $handler = new GetSubmissionHandler($harness->submissions);

        $view = $handler(new GetSubmission(Harness::tenant(), SubmissionId::fromString('sub-1')));

        static::assertSame('sub-1', $view->submissionId);
        static::assertSame('uploading', $view->state);
        static::assertSame('tenant-1', $view->tenantId);
    }

    public function testASubmissionOfAnotherTenantIsNotFound(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::Uploading);

        $this->expectException(RecordNotFound::class);

        (new GetSubmissionHandler($harness->submissions))(new GetSubmission(Harness::tenant(Harness::OTHER_TENANT), SubmissionId::fromString('sub-1')));
    }

    public function testFindsTheSubmissionsOfATenantInAState(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::Uploading, Harness::TENANT, '2026-01-15T08:00:00Z');
        $harness->identification(2, SubmissionState::InProcess, Harness::TENANT, '2026-01-15T09:00:00Z');
        $harness->identification(3, SubmissionState::Uploading, Harness::TENANT, '2026-01-15T09:30:00Z');
        $harness->identification(4, SubmissionState::Uploading, Harness::OTHER_TENANT);
        $handler = new FindSubmissionsHandler($harness->submissions, new WorkflowPolicy());

        $uploading = $handler(new FindSubmissions(Harness::tenant(), SubmissionState::Uploading, null, 0));
        $all = $handler(new FindSubmissions(Harness::tenant(), null, null, 0));

        static::assertSame(['sub-3', 'sub-1'], self::ids($uploading));
        static::assertSame(['sub-3', 'sub-2', 'sub-1'], self::ids($all));
    }

    public function testLimitAndOffsetPageTheSubmissions(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::Uploading, Harness::TENANT, '2026-01-15T08:00:00Z');
        $harness->identification(2, SubmissionState::Uploading, Harness::TENANT, '2026-01-15T09:00:00Z');
        $harness->identification(3, SubmissionState::Uploading, Harness::TENANT, '2026-01-15T09:30:00Z');
        $handler = new FindSubmissionsHandler($harness->submissions, new WorkflowPolicy());

        $views = $handler(new FindSubmissions(Harness::tenant(), null, 1, 1));

        static::assertSame(['sub-2'], self::ids($views));
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::Uploading);
        $harness->identification(2, SubmissionState::Uploading);
        $handler = new FindSubmissionsHandler($harness->submissions, self::policyWithPageSize(1));

        static::assertCount(1, $handler(new FindSubmissions(Harness::tenant(), null, 50, 0)));
        static::assertCount(1, $handler(new FindSubmissions(Harness::tenant(), null, null, 0)));
    }

    public function testRefusesAnInvalidLimitOrOffset(): void
    {
        $handler = new FindSubmissionsHandler((new Harness())->submissions, new WorkflowPolicy());

        try {
            $handler(new FindSubmissions(Harness::tenant(), null, 0, 0));
            static::fail('The limit must be refused.');
        } catch (InvalidValue $refused) {
            static::assertSame('workflow_policy.limit.not_positive', $refused->violationCode());
        }
        try {
            $handler(new FindSubmissions(Harness::tenant(), null, null, -1));
            static::fail('The offset must be refused.');
        } catch (InvalidValue $refused) {
            static::assertSame('offset.negative', $refused->violationCode());
        }
    }

    private static function policyWithPageSize(int $size): WorkflowPolicy
    {
        return new WorkflowPolicy(
            new DateInterval('PT10M'),
            new DateInterval('PT6H'),
            new DateInterval('P1Y'),
            5,
            new DateInterval('PT1H'),
            new DateInterval('P1D'),
            1,
            true,
            new DateInterval('P30D'),
            $size,
            $size,
        );
    }

    /**
     * @param list<SubmissionView> $views
     *
     * @return list<string>
     */
    private static function ids(array $views): array
    {
        return array_map(static fn(SubmissionView $view): string => $view->submissionId, $views);
    }
}
