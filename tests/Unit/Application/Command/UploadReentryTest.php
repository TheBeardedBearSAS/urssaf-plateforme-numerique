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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\SubmissionAlreadyRecorded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * The re-entry table of ADR 0004: a second upload under the same external reference.
 */
final class UploadReentryTest extends TestCase
{
    /**
     * @return iterable<string, array{SubmissionState}>
     */
    public static function replayedStates(): iterable
    {
        yield 'in process' => [SubmissionState::InProcess];
        yield 'accepted' => [SubmissionState::Accepted];
        yield 'rejected' => [SubmissionState::Rejected];
        yield 'outcome unknown' => [SubmissionState::OutcomeUnknown];
    }

    /**
     * @return iterable<string, array{SubmissionState}>
     */
    public static function reclaimableStates(): iterable
    {
        yield 'upload rejected' => [SubmissionState::UploadRejected];
        yield 'error' => [SubmissionState::Error];
        yield 'invalid credentials' => [SubmissionState::InvalidCredentials];
        yield 'not received' => [SubmissionState::NotReceived];
    }

    #[DataProvider('replayedStates')]
    public function testTheSameFileHeldOrMaybeHeldByUrssafIsReplayedWithoutBeingSent(SubmissionState $state): void
    {
        $harness = UploadScenario::recorded($state);

        $report = UploadScenario::identification($harness, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::Replayed, $report->outcome);
        static::assertSame(UploadScenario::FIRST_ID, $report->submissionId->value());
        static::assertSame($state, $report->state);
        static::assertCount(1, $harness->gateway->recorded()->calls());
        static::assertSame(['begin', 'commit'], $harness->journal->entries());
    }

    public function testTheSameFileBeingSentByAnotherProcessIsNotClaimed(): void
    {
        $harness = UploadScenario::uploading();

        $report = UploadScenario::identification($harness, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::NotClaimed, $report->outcome);
        static::assertSame(SubmissionState::Uploading, $report->state);
        static::assertCount(0, $harness->gateway->recorded()->calls());
    }

    #[DataProvider('reclaimableStates')]
    public function testTheSameFileNotHeldByUrssafIsReclaimedAndSentAgain(SubmissionState $state): void
    {
        $harness = UploadScenario::recorded($state);
        $harness->gateway->willUploadIdentification(UrssafReference::fromString('urssaf-again'));

        $report = UploadScenario::identification($harness, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::Uploaded, $report->outcome);
        static::assertSame(UploadScenario::FIRST_ID, $report->submissionId->value());
        $stored = UploadScenario::stored($harness, UploadScenario::FIRST_ID);
        static::assertSame(SubmissionState::InProcess, $stored->state());
        static::assertSame('urssaf-again', $stored->urssafReference()?->value());
        static::assertSame(2, $stored->attempts());
        static::assertSame([], $stored->errors());
        static::assertCount(2, $harness->gateway->recorded()->calls());
        static::assertSame(['begin', 'commit', 'upload_identification:' . UploadScenario::FILE, 'begin', 'commit', 'publish:SubmissionUploaded'], $harness->journal->entries());
    }

    #[DataProvider('replayedStates')]
    #[DataProvider('reclaimableStates')]
    public function testAnotherFileUnderTheSameReferenceIsRefused(SubmissionState $state): void
    {
        $harness = UploadScenario::recorded($state);

        try {
            UploadScenario::identification($harness, UploadScenario::file('other content'));
            static::fail('Another file under the same reference must be refused.');
        } catch (SubmissionAlreadyRecorded $refused) {
            static::assertSame('submission.already_recorded', $refused->violationCode());
            static::assertSame(['external_reference' => UploadScenario::REFERENCE, 'submission_id' => UploadScenario::FIRST_ID], $refused->params());
        }

        static::assertCount(1, $harness->gateway->recorded()->calls());
        static::assertSame($state, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->state());
    }

    public function testAnotherFileIsRefusedEvenWhileTheFirstOneIsBeingSent(): void
    {
        $harness = UploadScenario::uploading();

        $this->expectException(SubmissionAlreadyRecorded::class);

        UploadScenario::identification($harness, UploadScenario::file('other content'));
    }
}
