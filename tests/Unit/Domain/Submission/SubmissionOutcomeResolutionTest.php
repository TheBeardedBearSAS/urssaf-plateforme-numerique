<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionNotReceived;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

final class SubmissionOutcomeResolutionTest extends TestCase
{
    private const LATER = '2026-01-15T10:05:00Z';

    public function testAdoptReference(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::OutcomeUnknown, SubmissionFixtures::declaration());
        $reference = UrssafReference::fromString('urssaf-ref-2');
        $submission->adoptReference($reference, SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::InProcess, $submission->state());
        static::assertSame($reference, $submission->urssafReference());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        $this->assertUploadedEvent($submission, $reference);
    }

    public function testAdoptReferenceRequiresOutcomeUnknown(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());

        $this->expectExceptionObject(InvalidStateTransition::between('submission', 'uploading', 'in_process'));

        $submission->adoptReference(SubmissionFixtures::reference(), SubmissionFixtures::now());
    }

    public function testMarkNotReceived(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::OutcomeUnknown, SubmissionFixtures::declaration());
        $submission->markNotReceived(SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::NotReceived, $submission->state());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        $events = $submission->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(SubmissionNotReceived::class, $event);
        static::assertSame($submission->identity(), $event->identity());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testMarkNotReceivedRequiresOutcomeUnknown(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());

        $this->expectExceptionObject(InvalidStateTransition::between('submission', 'uploading', 'not_received'));

        $submission->markNotReceived(SubmissionFixtures::now());
    }

    private function assertUploadedEvent(Submission $submission, UrssafReference $reference): void
    {
        $events = $submission->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(SubmissionUploaded::class, $event);
        static::assertSame($submission->identity(), $event->identity());
        static::assertSame($reference, $event->urssafReference());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }
}
