<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

final class SubmissionPollWithoutAnswerTest extends TestCase
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
    public function testRecordPollWithoutAnswerRequiresAStateKnownToUrssaf(SubmissionState $state): void
    {
        $submission = SubmissionFixtures::submissionIn($state, SubmissionFixtures::declaration());

        try {
            $submission->recordPollWithoutAnswer(SubmissionFixtures::now());
            static::fail('The poll must be refused.');
        } catch (InvalidStateTransition $exception) {
            static::assertEquals(InvalidStateTransition::between('submission', $state->value, 'in_process'), $exception);
        }
        static::assertNull($submission->lastPolledAt());
    }

    public function testAPollWithoutAnswerOnlyMovesThePollInstant(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::InProcess, SubmissionFixtures::declaration());
        $snapshot = SubmissionFixtures::snapshot('IN_PROCESS', 'PENDING');
        $submission->applySnapshot($snapshot, SubmissionFixtures::now());
        $submission->releaseEvents();
        $updatedAt = $submission->updatedAt();

        $submission->recordPollWithoutAnswer(SubmissionFixtures::at(self::LATER));

        static::assertSame(self::LATER, $submission->lastPolledAt()?->format());
        static::assertSame(SubmissionState::InProcess, $submission->state());
        static::assertSame($snapshot, $submission->snapshot());
        static::assertSame($updatedAt, $submission->updatedAt());
        static::assertSame([], $submission->releaseEvents());
    }
}
