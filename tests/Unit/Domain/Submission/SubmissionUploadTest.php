<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ContentFingerprint;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionOutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploadRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

final class SubmissionUploadTest extends TestCase
{
    private const LATER = '2026-01-15T10:05:00Z';

    public function testStart(): void
    {
        $identity = SubmissionFixtures::declaration();
        $now = SubmissionFixtures::now();
        $submission = Submission::start($identity, $now);

        static::assertSame($identity, $submission->identity());
        static::assertSame($identity->id(), $submission->id());
        static::assertSame($identity->tenantId(), $submission->tenantId());
        static::assertSame($identity->externalReference(), $submission->externalReference());
        static::assertSame(SubmissionState::Uploading, $submission->state());
        static::assertSame(1, $submission->attempts());
        static::assertNull($submission->urssafReference());
        static::assertSame([], $submission->errors());
        static::assertNull($submission->snapshot());
        static::assertSame($now, $submission->createdAt());
        static::assertSame($now, $submission->updatedAt());
        static::assertNull($submission->lastPolledAt());
        static::assertSame([], $submission->releaseEvents());
    }

    public function testMarkUploaded(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());
        $reference = SubmissionFixtures::reference();
        $submission->markUploaded($reference, SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::InProcess, $submission->state());
        static::assertSame($reference, $submission->urssafReference());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        static::assertSame(SubmissionFixtures::NOW, $submission->createdAt()->format());
        $this->assertUploadedEvent($submission, $reference);
    }

    public function testMarkUploadedRequiresUploading(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::OutcomeUnknown, SubmissionFixtures::declaration());

        $this->expectExceptionObject(InvalidStateTransition::between('submission', 'outcome_unknown', 'in_process'));

        $submission->markUploaded(SubmissionFixtures::reference(), SubmissionFixtures::now());
    }

    public function testMarkUploadedFromAKnownState(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::declaration());

        $this->expectExceptionObject(InvalidStateTransition::between('submission', 'in_process', 'in_process'));

        $submission->markUploaded(SubmissionFixtures::reference(), SubmissionFixtures::now());
    }

    public function testMarkUploadRejected(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());
        $errors = [SubmissionError::of('ERR_CODE', 'A message.')];
        $submission->markUploadRejected($errors, SubmissionFixtures::at(self::LATER));

        static::assertSame(SubmissionState::UploadRejected, $submission->state());
        static::assertSame($errors, $submission->errors());
        static::assertNull($submission->urssafReference());
        static::assertSame(self::LATER, $submission->updatedAt()->format());
        $events = $submission->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(SubmissionUploadRejected::class, $event);
        static::assertSame($submission->identity(), $event->identity());
        static::assertSame($errors, $event->errors());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testMarkUploadRejectedRequiresUploading(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Error, SubmissionFixtures::declaration());

        $this->expectExceptionObject(InvalidStateTransition::between('submission', 'error', 'upload_rejected'));

        $submission->markUploadRejected([], SubmissionFixtures::now());
    }

    public function testMatchesFingerprint(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());

        static::assertTrue($submission->matchesFingerprint(ContentFingerprint::fromHex(SubmissionFixtures::FINGERPRINT)));
        static::assertFalse($submission->matchesFingerprint(ContentFingerprint::fromHex(str_repeat('b', 64))));
    }

    public function testEventsAreReleasedOnce(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());
        $submission->markOutcomeUnknown(SubmissionFixtures::now());
        $submission->adoptReference(SubmissionFixtures::reference(), SubmissionFixtures::now());
        $events = $submission->releaseEvents();

        static::assertCount(2, $events);
        static::assertInstanceOf(SubmissionOutcomeUnknown::class, $events[0]);
        static::assertInstanceOf(SubmissionUploaded::class, $events[1]);
        static::assertSame([], $submission->releaseEvents());
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
