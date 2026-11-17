<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\RacingSubmissionRepository;

/**
 * Re-entries bounded by the attempts, and racing another process (ADR 0004).
 */
final class UploadRaceTest extends TestCase
{
    public function testTheRecordIsNotSentAgainOnceTheMaximumOfAttemptsIsReached(): void
    {
        $harness = new Harness(UploadScenario::policy(2));
        $rejected = UrssafRequestRejected::withErrors(Operation::UploadIdentification, 400, ApiErrors::none());
        $harness->gateway->willUploadIdentification($rejected, $rejected);
        UploadScenario::identification($harness, UploadScenario::file('content'));
        UploadScenario::identification($harness, UploadScenario::file('content'));
        $harness->journal->clear();

        $report = UploadScenario::identification($harness, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::Exhausted, $report->outcome);
        static::assertSame(SubmissionState::UploadRejected, $report->state);
        static::assertSame(2, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->attempts());
        static::assertCount(2, $harness->gateway->recorded()->calls());
        static::assertSame(['begin', 'commit'], $harness->journal->entries());
    }

    public function testAClaimLostToAnotherProcessIsReportedAsStoredAndNothingIsSent(): void
    {
        $harness = UploadScenario::recorded(SubmissionState::UploadRejected);
        $racing = (new RacingSubmissionRepository($harness->submissions))->loseNextClaims(1);

        $report = UploadScenario::identificationThrough(UploadScenario::uploaderOver($harness, $racing), UploadScenario::file('content'));

        static::assertSame(UploadOutcome::NotClaimed, $report->outcome);
        static::assertSame(SubmissionState::UploadRejected, $report->state);
        static::assertSame(1, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->attempts());
        static::assertCount(1, $harness->gateway->recorded()->calls());
    }

    public function testARecordCreatedConcurrentlyIsHandledAsAReentry(): void
    {
        $harness = UploadScenario::recorded(SubmissionState::InProcess);
        $racing = (new RacingSubmissionRepository($harness->submissions))->hideNextLookups(1);

        $report = UploadScenario::identificationThrough(UploadScenario::uploaderOver($harness, $racing), UploadScenario::file('content'));

        static::assertSame(UploadOutcome::Replayed, $report->outcome);
        static::assertSame(UploadScenario::FIRST_ID, $report->submissionId->value());
        static::assertSame(['begin', 'commit'], $harness->journal->entries());
    }

    public function testADuplicateThatCannotBeReadAgainPropagates(): void
    {
        $harness = UploadScenario::recorded(SubmissionState::InProcess);
        $racing = (new RacingSubmissionRepository($harness->submissions))->hideNextLookups(2);

        try {
            UploadScenario::identificationThrough(UploadScenario::uploaderOver($harness, $racing), UploadScenario::file('content'));
            static::fail('The duplicate must propagate.');
        } catch (InvariantViolation $duplicate) {
            static::assertSame('submission.duplicate_external_reference', $duplicate->violationCode());
        }

        static::assertSame(['begin', 'rollback'], $harness->journal->entries());
    }
}
