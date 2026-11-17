<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

final class SubmissionReclaimTest extends TestCase
{
    private const LATER = '2026-01-15T10:05:00Z';

    /**
     * @return iterable<string, array{SubmissionState}>
     */
    public static function provideReclaimableStates(): iterable
    {
        yield 'upload_rejected' => [SubmissionState::UploadRejected];
        yield 'error' => [SubmissionState::Error];
        yield 'invalid_credentials' => [SubmissionState::InvalidCredentials];
        yield 'not_received' => [SubmissionState::NotReceived];
    }

    #[DataProvider('provideReclaimableStates')]
    public function testReclaim(SubmissionState $state): void
    {
        $submission = SubmissionFixtures::submissionIn($state, SubmissionFixtures::declaration());

        static::assertTrue($submission->canReclaim(2));
        static::assertFalse($submission->canReclaim(1));

        $submission->reclaim(SubmissionFixtures::at(self::LATER), 2);

        static::assertSame(SubmissionState::Uploading, $submission->state());
        static::assertSame(2, $submission->attempts());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        static::assertSame([], $submission->releaseEvents());
        static::assertFalse($submission->canReclaim(3));
    }

    public function testReclaimClearsTheErrors(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());
        $submission->markUploadRejected([SubmissionError::of('ERR_CODE', 'A message.')], SubmissionFixtures::now());
        $submission->reclaim(SubmissionFixtures::now(), 5);

        static::assertSame([], $submission->errors());
    }

    public function testReclaimUntilExhausted(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Error, SubmissionFixtures::declaration());
        $submission->reclaim(SubmissionFixtures::now(), 3);
        $submission->markFailed(SubmissionFixtures::now());
        $submission->reclaim(SubmissionFixtures::now(), 3);
        $submission->markFailed(SubmissionFixtures::at(self::LATER));

        static::assertSame(3, $submission->attempts());
        static::assertFalse($submission->canReclaim(3));
        static::assertTrue($submission->canReclaim(4));

        try {
            $submission->reclaim(SubmissionFixtures::now(), 3);
            static::fail('The reclaim must be refused.');
        } catch (InvariantViolation $exception) {
            static::assertSame('submission.attempts_exhausted', $exception->violationCode());
            static::assertSame('The submission was uploaded 3 times: it is not uploaded again automatically.', $exception->getMessage());
            static::assertSame(['attempts' => 3, 'max_attempts' => 3], $exception->params());
        }
        static::assertSame(SubmissionState::Error, $submission->state());
        static::assertSame(3, $submission->attempts());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
    }

    /**
     * @return iterable<string, array{SubmissionState}>
     */
    public static function provideNonReclaimableStates(): iterable
    {
        yield 'uploading' => [SubmissionState::Uploading];
        yield 'in_process' => [SubmissionState::InProcess];
        yield 'accepted' => [SubmissionState::Accepted];
        yield 'rejected' => [SubmissionState::Rejected];
        yield 'outcome_unknown' => [SubmissionState::OutcomeUnknown];
    }

    #[DataProvider('provideNonReclaimableStates')]
    public function testReclaimRequiresAReclaimableState(SubmissionState $state): void
    {
        $submission = SubmissionFixtures::submissionIn($state, SubmissionFixtures::declaration());

        static::assertFalse($submission->canReclaim(5));
        $this->expectExceptionObject(InvalidStateTransition::between('submission', $state->value, 'uploading'));

        $submission->reclaim(SubmissionFixtures::now(), 5);
    }

    public function testTheStateIsCheckedBeforeTheAttempts(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::declaration());

        $this->expectExceptionObject(InvalidStateTransition::between('submission', 'in_process', 'uploading'));

        $submission->reclaim(SubmissionFixtures::now(), 1);
    }
}
