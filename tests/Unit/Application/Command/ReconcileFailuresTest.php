<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissionsHandler as Handler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReportItem;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

final class ReconcileFailuresTest extends TestCase
{
    public function testARefusedSearchLeavesTheSubmissionAndTheRunGoesOn(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown);
        $harness->identification(2, SubmissionState::OutcomeUnknown);
        $harness->gateway->willSearchIdentifications(
            UrssafRequestRejected::withErrors(Operation::SearchIdentifications, 400, ApiErrors::none()),
            ReadModels::noIdentifications(),
        );

        $report = ReconcileScenario::reconcile($harness, null);

        static::assertEquals([new ReportItem('sub-1', Handler::FAILED), new ReportItem('sub-2', Handler::UNMATCHED)], $report->items());
    }

    public function testRefusedCredentialsStopTheRunWithTheReportSoFar(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown);
        $harness->identification(2, SubmissionState::OutcomeUnknown);
        $harness->identification(3, SubmissionState::OutcomeUnknown);
        $harness->gateway->willSearchIdentifications(ReadModels::noIdentifications(), UrssafAuthenticationFailed::withStatus(403));

        $report = ReconcileScenario::reconcile($harness, null);

        static::assertEquals([new ReportItem('sub-1', Handler::UNMATCHED), new ReportItem('sub-2', Handler::INVALID_CREDENTIALS)], $report->items());
    }

    public function testATemporaryFailureStopsTheRunAndKeepsTheStaleUploadMovedToUnknown(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::Uploading, Harness::TENANT, '2026-01-15T08:00:00Z');
        $unavailable = UrssafUnavailable::withStatus(Operation::SearchIdentifications, 503, null, ApiErrors::none());
        $harness->gateway->willSearchIdentifications($unavailable);

        try {
            ReconcileScenario::reconcile($harness, null);
            static::fail('The temporary failure must be thrown.');
        } catch (RetryableUrssafFailure $failure) {
            static::assertSame($unavailable, $failure->getPrevious());
        }

        static::assertSame(SubmissionState::OutcomeUnknown, ReconcileScenario::stored($harness, 'sub-1')->state());
    }
}
