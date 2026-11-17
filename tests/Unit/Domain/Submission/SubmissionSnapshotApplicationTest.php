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

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionAccepted;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PollingWindow;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

final class SubmissionSnapshotApplicationTest extends TestCase
{
    private const LATER = '2026-01-15T10:05:00Z';

    /**
     * @return iterable<string, array{SubmissionState}>
     */
    public static function provideStatesUnknownToUrssaf(): iterable
    {
        yield 'uploading' => [SubmissionState::Uploading];
        yield 'upload_rejected' => [SubmissionState::UploadRejected];
        yield 'error' => [SubmissionState::Error];
        yield 'invalid_credentials' => [SubmissionState::InvalidCredentials];
        yield 'outcome_unknown' => [SubmissionState::OutcomeUnknown];
        yield 'not_received' => [SubmissionState::NotReceived];
    }

    #[DataProvider('provideStatesUnknownToUrssaf')]
    public function testApplySnapshotRequiresAStateKnownToUrssaf(SubmissionState $state): void
    {
        $submission = SubmissionFixtures::submissionIn($state, SubmissionFixtures::declaration());

        try {
            $submission->applySnapshot(SubmissionFixtures::snapshot('ACCEPTED', 'PROCESSED'), SubmissionFixtures::now());
            static::fail('The snapshot must be refused.');
        } catch (InvalidStateTransition $exception) {
            static::assertEquals(InvalidStateTransition::between('submission', $state->value, 'in_process'), $exception);
        }
        static::assertSame($state, $submission->state());
        static::assertNull($submission->snapshot());
        static::assertNull($submission->lastPolledAt());
    }

    #[DataProvider('provideStatesUnknownToUrssaf')]
    public function testStatesUnknownToUrssafAreNotPolled(SubmissionState $state): void
    {
        $submission = SubmissionFixtures::submissionIn($state, SubmissionFixtures::declaration());

        static::assertFalse($submission->needsPolling(SubmissionFixtures::at('2026-01-16T10:00:00Z'), $this->window()));
    }

    public function testAnInProcessStatusKeepsTheState(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::identification());
        $snapshot = SubmissionFixtures::snapshot('IN_PROCESS', null);
        $submission->applySnapshot($snapshot, SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::InProcess, $submission->state());
        static::assertSame($snapshot, $submission->snapshot());
        static::assertSame(self::LATER, $submission->lastPolledAt()?->format());
        static::assertSame(SubmissionFixtures::NOW, $submission->updatedAt()->format());
        static::assertSame([], $submission->releaseEvents());
    }

    public function testAccepted(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::declaration());
        $snapshot = SubmissionFixtures::snapshot('ACCEPTED', null);
        $submission->applySnapshot($snapshot, SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::Accepted, $submission->state());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        $events = $submission->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(SubmissionAccepted::class, $event);
        static::assertSame($submission->identity(), $event->identity());
        static::assertSame($submission->urssafReference(), $event->urssafReference());
        static::assertSame($snapshot, $event->snapshot());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testRejected(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::identification());
        $snapshot = SubmissionFixtures::snapshot('REJECTED', null);
        $submission->applySnapshot($snapshot, SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::Rejected, $submission->state());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        $events = $submission->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(SubmissionRejected::class, $event);
        static::assertSame($submission->identity(), $event->identity());
        static::assertSame($submission->urssafReference(), $event->urssafReference());
        static::assertSame($snapshot, $event->snapshot());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testTheOrderOfTheFinalStatusesIsNotEnforced(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Accepted, SubmissionFixtures::identification());
        $submission->applySnapshot(SubmissionFixtures::snapshot('REJECTED', null), SubmissionFixtures::now());
        static::assertSame(SubmissionState::Rejected, $submission->state());
        static::assertInstanceOf(SubmissionRejected::class, $submission->releaseEvents()[0] ?? null);

        $submission->applySnapshot(SubmissionFixtures::snapshot('ACCEPTED', null), SubmissionFixtures::now());
        static::assertSame(SubmissionState::Accepted, $submission->state());
        static::assertInstanceOf(SubmissionAccepted::class, $submission->releaseEvents()[0] ?? null);
    }

    /**
     * @return iterable<string, array{SubmissionState, ?string}>
     */
    public static function provideUnchangedStates(): iterable
    {
        yield 'accepted again' => [SubmissionState::Accepted, 'ACCEPTED'];
        yield 'rejected again' => [SubmissionState::Rejected, 'REJECTED'];
        yield 'accepted, then in process' => [SubmissionState::Accepted, 'IN_PROCESS'];
        yield 'rejected, then unknown' => [SubmissionState::Rejected, 'SOMETHING_ELSE'];
        yield 'accepted, then missing' => [SubmissionState::Accepted, null];
    }

    #[DataProvider('provideUnchangedStates')]
    public function testAnUnchangedStateRecordsNothing(SubmissionState $state, ?string $status): void
    {
        $submission = SubmissionFixtures::submissionIn($state, SubmissionFixtures::declaration());
        $snapshot = SubmissionFixtures::snapshot($status, null);
        $submission->applySnapshot($snapshot, SubmissionFixtures::at(self::LATER));

        static::assertSame($state, $submission->state());
        static::assertSame($snapshot, $submission->snapshot());
        static::assertSame(self::LATER, $submission->lastPolledAt()?->format());
        static::assertSame(SubmissionFixtures::NOW, $submission->updatedAt()->format());
        static::assertSame([], $submission->releaseEvents());
    }

    private function window(): PollingWindow
    {
        return PollingWindow::of(new DateInterval('PT10M'), new DateInterval('PT6H'), new DateInterval('P1D'));
    }
}
