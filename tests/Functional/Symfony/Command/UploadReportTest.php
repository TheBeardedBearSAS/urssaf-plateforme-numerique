<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadReport;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CommandReports;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness as Fakes;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\SubmissionSamples;

final class UploadReportTest extends TestCase
{
    #[DataProvider('uploads')]
    public function testAnUploadFailsUnlessItIsFineOrNothingWasSent(UploadOutcome $outcome, int $status): void
    {
        $submission = (new Fakes())->identification(1, SubmissionState::InProcess);
        $output = new BufferedOutput();

        static::assertSame($status, CommandReports::renderUpload($output, UploadReport::of($submission, $outcome)));

        static::assertSame($outcome->value, Output::rows($output->fetch())[2][1]);
    }

    /**
     * @return iterable<string, array{UploadOutcome, int}>
     */
    public static function uploads(): iterable
    {
        yield 'uploaded' => [UploadOutcome::Uploaded, 0];
        yield 'replayed' => [UploadOutcome::Replayed, 0];
        yield 'not claimed' => [UploadOutcome::NotClaimed, 0];
        yield 'rejected' => [UploadOutcome::Rejected, 1];
        yield 'invalid credentials' => [UploadOutcome::InvalidCredentials, 1];
        yield 'outcome unknown' => [UploadOutcome::OutcomeUnknown, 1];
        yield 'exhausted' => [UploadOutcome::Exhausted, 1];
    }

    public function testAnUploadPrintsTheRecordAndNoErrorDescription(): void
    {
        $submission = (new Fakes())->identification(1, SubmissionState::InProcess);
        $output = new BufferedOutput();

        CommandReports::renderUpload($output, UploadReport::of($submission, UploadOutcome::Uploaded));

        static::assertSame([
            ['Field', 'Value'],
            ['Submission', $submission->id()->value()],
            ['Outcome', 'uploaded'],
            ['State', 'in_process'],
            ['URSSAF reference', 'urssaf-1'],
            ['Errors', '-'],
        ], Output::rows($output->fetch()));
    }

    public function testARejectedUploadPrintsItsErrorCodesOnly(): void
    {
        $submission = Submission::start(SubmissionSamples::identity(1, SubmissionKind::Identification, null, 'tenant-1'), Instant::fromIso8601('2026-01-15T10:00:00Z'));
        $submission->markUploadRejected([SubmissionError::of('ERR_A', 'A description'), SubmissionError::of(null, 'Another description')], Instant::fromIso8601('2026-01-15T10:00:00Z'));
        $output = new BufferedOutput();

        $status = CommandReports::renderUpload($output, UploadReport::of($submission, UploadOutcome::Rejected));

        $display = $output->fetch();
        static::assertSame(1, $status);
        static::assertContains(['Errors', 'ERR_A, -'], Output::rows($display));
        static::assertStringNotContainsString('description', $display);
        static::assertStringNotContainsString('do not upload it again', $display);
    }

    public function testAnUnknownOutcomeTellsNotToUploadAgain(): void
    {
        $submission = (new Fakes())->identification(1, SubmissionState::OutcomeUnknown);
        $output = new BufferedOutput();

        CommandReports::renderUpload($output, UploadReport::of($submission, UploadOutcome::OutcomeUnknown));

        static::assertStringContainsString('The file may have reached URSSAF: do not upload it again, reconcile it ("urssaf:pn:submissions:reconcile") or resolve it ("urssaf:pn:submission:resolve").', $output->fetch());
    }
}
