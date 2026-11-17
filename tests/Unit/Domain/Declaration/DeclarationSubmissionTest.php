<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\PaymentState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingDates;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Uploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

final class DeclarationSubmissionTest extends TestCase
{
    public function testGetters(): void
    {
        $periods = [ReportedPeriod::fromWire('2025-01'), ReportedPeriod::fromWire('2025-02')];
        $reference = UrssafReference::fromString('cbcf2700-110d-4abf-9835-a9b46e68d7c4');
        $uploader = Uploader::of('user-1', 'jane.doe@example.com');
        $status = ProcessingStatus::of('ACCEPTED', 'Accepted');
        $payment = PaymentState::of('PROCESSED', 'Processed');
        $dates = ProcessingDates::of(null, null, null);
        $files = ReportFiles::none();
        $statistics = DeclarationStatistics::of(null, null, null, null, null);

        $submission = new DeclarationSubmission(
            'file-ref-1',
            'declaration.csv',
            'REGULARISATION',
            $periods,
            $reference,
            $uploader,
            $status,
            $payment,
            $dates,
            $files,
            $statistics,
            'regularisation-ref-1',
        );

        static::assertSame('file-ref-1', $submission->declarationFileRef());
        static::assertSame('declaration.csv', $submission->declarationFileName());
        static::assertSame('REGULARISATION', $submission->rawDeclarationType());
        static::assertSame(DeclarationType::Regularisation, $submission->declarationType());
        static::assertSame($periods, $submission->periods());
        static::assertSame($reference, $submission->urssafRef());
        static::assertSame($uploader, $submission->uploader());
        static::assertSame($status, $submission->processingStatus());
        static::assertSame($payment, $submission->paymentState());
        static::assertSame($dates, $submission->dates());
        static::assertSame($files, $submission->reportFiles());
        static::assertSame($statistics, $submission->statistics());
        static::assertSame('regularisation-ref-1', $submission->concernedRegularisation());
    }

    public function testUnknownTypeKeepsRawValue(): void
    {
        $submission = $this->minimal('SOMETHING_NEW');

        static::assertSame('SOMETHING_NEW', $submission->rawDeclarationType());
        static::assertNull($submission->declarationType());
    }

    public function testOptionalFieldsAbsent(): void
    {
        $submission = $this->minimal(null);

        static::assertNull($submission->declarationFileRef());
        static::assertNull($submission->declarationFileName());
        static::assertNull($submission->rawDeclarationType());
        static::assertNull($submission->declarationType());
        static::assertSame([], $submission->periods());
        static::assertNull($submission->urssafRef());
        static::assertNull($submission->statistics());
        static::assertNull($submission->concernedRegularisation());
    }

    public function testDebugInfoMasksUploader(): void
    {
        $submission = new DeclarationSubmission(
            'file-ref-1',
            'declaration.csv',
            'DECLARATION',
            [],
            null,
            Uploader::of('user-1', 'jane.doe@example.com'),
            ProcessingStatus::of(null, null),
            PaymentState::of(null, null),
            ProcessingDates::of(null, null, null),
            ReportFiles::none(),
            null,
            'regularisation-ref-1',
        );

        $info = $submission->__debugInfo();

        static::assertSame(['userId' => '***', 'userEmail' => '***'], $info['uploader']);
        static::assertSame('file-ref-1', $info['declarationFileRef']);
        static::assertSame('declaration.csv', $info['declarationFileName']);
        static::assertSame('DECLARATION', $info['rawDeclarationType']);
        static::assertSame('regularisation-ref-1', $info['concernedRegularisation']);
        static::assertSame(
            [
                'declarationFileRef',
                'declarationFileName',
                'rawDeclarationType',
                'periods',
                'urssafRef',
                'uploader',
                'processingStatus',
                'paymentState',
                'dates',
                'reportFiles',
                'statistics',
                'concernedRegularisation',
            ],
            array_keys($info),
        );
        $dump = print_r($submission, true);
        static::assertStringNotContainsString('jane.doe', $dump);
        static::assertStringNotContainsString('user-1', $dump);
    }

    private function minimal(?string $rawType): DeclarationSubmission
    {
        return new DeclarationSubmission(
            null,
            null,
            $rawType,
            [],
            null,
            Uploader::of(null, null),
            ProcessingStatus::of(null, null),
            PaymentState::of(null, null),
            ProcessingDates::of(null, null, null),
            ReportFiles::none(),
            null,
            null,
        );
    }
}
