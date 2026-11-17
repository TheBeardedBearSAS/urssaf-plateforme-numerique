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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionOutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploadFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

final class SubmissionUploadFailureTest extends TestCase
{
    private const LATER = '2026-01-15T10:05:00Z';

    public function testMarkFailed(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());
        $submission->markFailed(SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::Error, $submission->state());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        $this->assertFailedEvent($submission, SubmissionState::Error);
    }

    public function testMarkInvalidCredentials(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());
        $submission->markInvalidCredentials(SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::InvalidCredentials, $submission->state());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        $this->assertFailedEvent($submission, SubmissionState::InvalidCredentials);
    }

    public function testMarkFailedRequiresUploading(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::declaration());

        $this->expectExceptionObject(InvalidStateTransition::between('submission', 'in_process', 'error'));

        $submission->markFailed(SubmissionFixtures::now());
    }

    public function testMarkInvalidCredentialsRequiresUploading(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Error, SubmissionFixtures::declaration());

        $this->expectExceptionObject(InvalidStateTransition::between('submission', 'error', 'invalid_credentials'));

        $submission->markInvalidCredentials(SubmissionFixtures::now());
    }

    public function testMarkOutcomeUnknown(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());
        $submission->markOutcomeUnknown(SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::OutcomeUnknown, $submission->state());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        $events = $submission->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(SubmissionOutcomeUnknown::class, $event);
        static::assertSame($submission->identity(), $event->identity());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testMarkOutcomeUnknownRequiresUploading(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::OutcomeUnknown, SubmissionFixtures::declaration());

        $this->expectExceptionObject(InvalidStateTransition::between('submission', 'outcome_unknown', 'outcome_unknown'));

        $submission->markOutcomeUnknown(SubmissionFixtures::now());
    }

    private function assertFailedEvent(Submission $submission, SubmissionState $state): void
    {
        $events = $submission->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(SubmissionUploadFailed::class, $event);
        static::assertSame($submission->identity(), $event->identity());
        static::assertSame($state, $event->state());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }
}
