<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadReport;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

final class UploadReportTest extends TestCase
{
    public function testAnUploadedSubmission(): void
    {
        $submission = (new Harness())->declaration(1, SubmissionState::InProcess);

        $report = UploadReport::of($submission, UploadOutcome::Uploaded);

        static::assertSame($submission->id(), $report->submissionId);
        static::assertSame(UploadOutcome::Uploaded, $report->outcome);
        static::assertSame(SubmissionState::InProcess, $report->state);
        static::assertSame($submission->urssafReference(), $report->urssafReference);
        static::assertSame([], $report->errors);
    }

    public function testARejectedUploadCarriesItsErrors(): void
    {
        $submission = (new Harness())->identification(2, SubmissionState::UploadRejected);

        $report = UploadReport::of($submission, UploadOutcome::Rejected);

        static::assertSame(SubmissionState::UploadRejected, $report->state);
        static::assertNull($report->urssafReference);
        static::assertCount(1, $report->errors);
        static::assertSame('ERR_X', $report->errors[0]->code());
    }

    public function testTheErrorsOfAnotherOutcomeAreNotReported(): void
    {
        $submission = (new Harness())->identification(3, SubmissionState::UploadRejected);

        static::assertSame([], UploadReport::of($submission, UploadOutcome::Exhausted)->errors);
    }

    public function testTheOutcomesAreTheDocumentedOnes(): void
    {
        static::assertSame(
            ['uploaded', 'replayed', 'not_claimed', 'rejected', 'invalid_credentials', 'outcome_unknown', 'exhausted'],
            array_map(static fn(UploadOutcome $outcome): string => $outcome->value, UploadOutcome::cases()),
        );
    }
}
