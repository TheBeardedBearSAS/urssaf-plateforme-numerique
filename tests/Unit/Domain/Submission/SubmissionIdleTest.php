<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

final class SubmissionIdleTest extends TestCase
{
    private const LATER = '2026-01-15T10:05:00Z';

    public function testIsStaleUpload(): void
    {
        $after = new DateInterval('PT5M');
        $uploading = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());

        static::assertFalse($uploading->isStaleUpload(SubmissionFixtures::at('2026-01-15T10:04:59Z'), $after));
        static::assertTrue($uploading->isStaleUpload(SubmissionFixtures::at('2026-01-15T10:05:00Z'), $after));
        static::assertFalse($this->outcomeUnknown()->isStaleUpload(SubmissionFixtures::at('2026-01-15T11:00:00Z'), $after));
    }

    public function testNeedsManualReview(): void
    {
        $after = new DateInterval('PT5M');
        $unknown = $this->outcomeUnknown();

        static::assertFalse($unknown->needsManualReview(SubmissionFixtures::at('2026-01-15T10:04:59Z'), $after));
        static::assertTrue($unknown->needsManualReview(SubmissionFixtures::at('2026-01-15T10:05:00Z'), $after));
        $uploading = SubmissionFixtures::submissionIn(SubmissionState::Uploading, SubmissionFixtures::declaration());
        static::assertFalse($uploading->needsManualReview(SubmissionFixtures::at('2026-01-15T11:00:00Z'), $after));
    }

    public function testTheIdleDelayStartsAtTheLastUpdate(): void
    {
        $submission = SubmissionFixtures::submissionIn(SubmissionState::Error, SubmissionFixtures::declaration());
        $submission->reclaim(SubmissionFixtures::at(self::LATER), 5);

        static::assertFalse($submission->isStaleUpload(SubmissionFixtures::at('2026-01-15T10:09:59Z'), new DateInterval('PT5M')));
        static::assertTrue($submission->isStaleUpload(SubmissionFixtures::at('2026-01-15T10:10:00Z'), new DateInterval('PT5M')));
    }

    private function outcomeUnknown(): Submission
    {
        return SubmissionFixtures::submissionIn(SubmissionState::OutcomeUnknown, SubmissionFixtures::declaration());
    }
}
