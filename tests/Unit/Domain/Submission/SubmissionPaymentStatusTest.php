<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\PaymentStatusChanged;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionAccepted;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PollingWindow;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

final class SubmissionPaymentStatusTest extends TestCase
{
    private const LATER = '2026-01-15T10:05:00Z';

    public function testPaymentStatusChanges(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::declaration());
        $submission->applySnapshot(SubmissionFixtures::snapshot('IN_PROCESS', 'NOT_INITIATED'), SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::InProcess, $submission->state());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        $events = $submission->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(PaymentStatusChanged::class, $event);
        static::assertSame($submission->identity(), $event->identity());
        static::assertSame($submission->urssafReference(), $event->urssafReference());
        static::assertNull($event->previousRawPaymentStatus());
        static::assertSame('NOT_INITIATED', $event->rawPaymentStatus());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));

        $submission->applySnapshot(SubmissionFixtures::snapshot('IN_PROCESS', 'NOT_INITIATED'), SubmissionFixtures::at('2026-01-15T11:00:00Z'));
        static::assertSame([], $submission->releaseEvents());
        static::assertSame(self::LATER, $submission->updatedAt()->format());

        $submission->applySnapshot(SubmissionFixtures::snapshot('ACCEPTED', 'PROCESSING'), SubmissionFixtures::at('2026-01-15T12:00:00Z'));
        $events = $submission->releaseEvents();
        static::assertCount(2, $events);
        static::assertInstanceOf(SubmissionAccepted::class, $events[0]);
        $event = $events[1];
        static::assertInstanceOf(PaymentStatusChanged::class, $event);
        static::assertSame('NOT_INITIATED', $event->previousRawPaymentStatus());
        static::assertSame('PROCESSING', $event->rawPaymentStatus());
        static::assertSame('2026-01-15T12:00:00Z', $submission->updatedAt()->format());

        $submission->applySnapshot(SubmissionFixtures::snapshot('ACCEPTED', 'PROCESSED'), SubmissionFixtures::at('2026-01-15T13:00:00Z'));
        $events = $submission->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(PaymentStatusChanged::class, $events[0]);
        static::assertSame('2026-01-15T13:00:00Z', $submission->updatedAt()->format());
    }

    public function testThePaymentStatusOfAnIdentificationIsIgnored(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::identification());
        $submission->applySnapshot(SubmissionFixtures::snapshot('IN_PROCESS', 'PROCESSED'), SubmissionFixtures::at(self::LATER));

        static::assertSame([], $submission->releaseEvents());
        static::assertSame(SubmissionFixtures::NOW, $submission->updatedAt()->format());
    }

    public function testASettledDeclarationIsNotPolled(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::declaration());
        $submission->applySnapshot(SubmissionFixtures::snapshot('ACCEPTED', 'PROCESSED'), SubmissionFixtures::now());

        static::assertFalse($submission->needsPolling(SubmissionFixtures::at('2026-01-15T16:00:00Z'), $this->window()));
    }

    private function window(): PollingWindow
    {
        return PollingWindow::of(new DateInterval('PT10M'), new DateInterval('PT6H'), new DateInterval('P1D'));
    }
}
