<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\SubmissionSamples;

final class SubmissionViewTest extends TestCase
{
    public function testAPolledDeclaration(): void
    {
        $harness = new Harness();
        $submission = $harness->declaration(1, SubmissionState::InProcess, DeclarationType::Regularisation);
        $submission->applySnapshot(SubmissionSamples::snapshot('ACCEPTED', 'PROCESSED'), Harness::at('2026-01-16T09:00:00Z'));

        $view = SubmissionView::fromSubmission($submission);

        static::assertSame('sub-1', $view->submissionId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame('platform-1', $view->platformId);
        static::assertSame('ref-1', $view->externalReference);
        static::assertSame('declaration', $view->kind);
        static::assertSame('REGULARISATION', $view->declarationType);
        static::assertSame('file-1.zip', $view->fileName);
        static::assertSame('accepted', $view->state);
        static::assertSame(1, $view->attempts);
        static::assertSame('urssaf-1', $view->urssaf->urssafRef);
        static::assertSame('ACCEPTED', $view->urssaf->rawStatus);
        static::assertSame('PROCESSED', $view->urssaf->rawPaymentStatus);
        static::assertSame([], $view->errors);
        static::assertSame('2026-01-15T10:00:00Z', $view->timestamps->createdAt);
        static::assertSame('2026-01-16T09:00:00Z', $view->timestamps->updatedAt);
        static::assertSame('2026-01-16T09:00:00Z', $view->urssaf->lastPolledAt);
    }

    public function testARefusedIdentification(): void
    {
        $submission = (new Harness())->identification(2, SubmissionState::UploadRejected, Harness::OTHER_TENANT);
        $submission->reclaim(Harness::at('2026-01-15T11:00:00Z'), 5);
        $submission->markUploadRejected([SubmissionError::of('ERR_A', 'a'), SubmissionError::of(null, null)], Harness::at('2026-01-15T11:01:00Z'));

        $view = SubmissionView::fromSubmission($submission);

        static::assertSame('tenant-2', $view->tenantId);
        static::assertSame('platform-2', $view->platformId);
        static::assertSame('identification', $view->kind);
        static::assertNull($view->declarationType);
        static::assertSame('upload_rejected', $view->state);
        static::assertSame(2, $view->attempts);
        static::assertNull($view->urssaf->urssafRef);
        static::assertNull($view->urssaf->rawStatus);
        static::assertNull($view->urssaf->rawPaymentStatus);
        static::assertSame([['code' => 'ERR_A', 'message' => 'a'], ['code' => null, 'message' => null]], $view->errors);
        static::assertSame('2026-01-15T11:01:00Z', $view->timestamps->updatedAt);
        static::assertNull($view->urssaf->lastPolledAt);
    }

    public function testNoPersonalDataNorFingerprintIsExposed(): void
    {
        $view = SubmissionView::fromSubmission((new Harness())->identification(3, SubmissionState::Error));

        static::assertStringNotContainsString(SubmissionSamples::fingerprint(3)->value(), serialize($view));
        static::assertSame(
            ['submissionId', 'tenantId', 'platformId', 'externalReference', 'kind', 'declarationType', 'fileName', 'state', 'attempts', 'urssaf', 'errors', 'timestamps'],
            array_keys(get_object_vars($view)),
        );
    }
}
