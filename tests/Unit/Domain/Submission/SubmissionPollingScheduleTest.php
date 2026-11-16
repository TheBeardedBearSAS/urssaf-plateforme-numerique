<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PollingWindow;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

final class SubmissionPollingScheduleTest extends TestCase
{
    public function testAnInProcessSubmissionIsPolledAtTheStatusInterval(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::identification());

        static::assertTrue($submission->needsPolling(SubmissionFixtures::now(), $this->window()));

        $submission->applySnapshot(SubmissionFixtures::snapshot('IN_PROCESS', null), SubmissionFixtures::now());
        static::assertFalse($submission->needsPolling(SubmissionFixtures::at('2026-01-15T10:09:59Z'), $this->window()));
        static::assertTrue($submission->needsPolling(SubmissionFixtures::at('2026-01-15T10:10:00Z'), $this->window()));
    }

    public function testNoPollAfterTheMaximumAge(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::declaration());

        static::assertTrue($submission->needsPolling(SubmissionFixtures::at('2026-01-16T10:00:00Z'), $this->window()));
        static::assertFalse($submission->needsPolling(SubmissionFixtures::at('2026-01-16T10:00:01Z'), $this->window()));

        $accepted = SubmissionFixtures::submissionIn(SubmissionState::Accepted, SubmissionFixtures::declaration());
        static::assertTrue($accepted->needsPolling(SubmissionFixtures::at('2026-01-16T10:00:00Z'), $this->window()));
        static::assertFalse($accepted->needsPolling(SubmissionFixtures::at('2026-01-16T10:00:01Z'), $this->window()));
    }

    /**
     * @return iterable<string, array{SubmissionState}>
     */
    public static function provideProcessedStates(): iterable
    {
        yield 'accepted' => [SubmissionState::Accepted];
        yield 'rejected' => [SubmissionState::Rejected];
    }

    #[DataProvider('provideProcessedStates')]
    public function testAnUnsettledDeclarationIsPolledAtThePaymentInterval(SubmissionState $state): void
    {
        $submission = SubmissionFixtures::submissionIn($state, SubmissionFixtures::declaration());

        static::assertFalse($submission->needsPolling(SubmissionFixtures::at('2026-01-15T15:59:59Z'), $this->window()));
        static::assertTrue($submission->needsPolling(SubmissionFixtures::at('2026-01-15T16:00:00Z'), $this->window()));
    }

    #[DataProvider('provideProcessedStates')]
    public function testAProcessedIdentificationIsNotPolled(SubmissionState $state): void
    {
        $submission = SubmissionFixtures::submissionIn($state, SubmissionFixtures::identification());

        static::assertFalse($submission->needsPolling(SubmissionFixtures::at('2026-01-15T16:00:00Z'), $this->window()));
    }

    private function window(): PollingWindow
    {
        return PollingWindow::of(new DateInterval('PT10M'), new DateInterval('PT6H'), new DateInterval('P1D'));
    }
}
