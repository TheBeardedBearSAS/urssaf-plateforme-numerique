<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Identification;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationDetails;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingDates;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Uploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

final class IdentificationSubmissionTest extends TestCase
{
    public function testGetters(): void
    {
        $period = ReportedPeriod::fromWire('2025-01');
        $reference = UrssafReference::fromString('cbcf2700-110d-4abf-9835-a9b46e68d7c4');
        $uploader = Uploader::of('user-1', 'jane.doe@example.com');
        $status = ProcessingStatus::of('IN_PROCESS', 'In process');
        $dates = ProcessingDates::of(null, null, null);
        $files = ReportFiles::none();
        $details = IdentificationDetails::of(null, null, null);

        $submission = new IdentificationSubmission(
            'file-ref-1',
            'identification.csv',
            $period,
            $reference,
            $uploader,
            $status,
            $dates,
            $files,
            $details,
        );

        static::assertSame('file-ref-1', $submission->identificationFileRef());
        static::assertSame('identification.csv', $submission->identificationFileName());
        static::assertSame($period, $submission->period());
        static::assertSame($reference, $submission->urssafRef());
        static::assertSame($uploader, $submission->uploader());
        static::assertSame($status, $submission->processingStatus());
        static::assertSame($dates, $submission->dates());
        static::assertSame($files, $submission->reportFiles());
        static::assertSame($details, $submission->details());
    }

    public function testOptionalFieldsAbsent(): void
    {
        $submission = new IdentificationSubmission(
            null,
            null,
            null,
            null,
            Uploader::of(null, null),
            ProcessingStatus::of(null, null),
            ProcessingDates::of(null, null, null),
            ReportFiles::none(),
            null,
        );

        static::assertNull($submission->identificationFileRef());
        static::assertNull($submission->identificationFileName());
        static::assertNull($submission->period());
        static::assertNull($submission->urssafRef());
        static::assertNull($submission->details());
    }

    public function testDebugInfoMasksUploader(): void
    {
        $submission = new IdentificationSubmission(
            'file-ref-1',
            'identification.csv',
            null,
            null,
            Uploader::of('user-1', 'jane.doe@example.com'),
            ProcessingStatus::of(null, null),
            ProcessingDates::of(null, null, null),
            ReportFiles::none(),
            null,
        );

        $info = $submission->__debugInfo();

        static::assertSame(['userId' => '***', 'userEmail' => '***'], $info['uploader']);
        static::assertSame('file-ref-1', $info['identificationFileRef']);
        static::assertSame('identification.csv', $info['identificationFileName']);
        static::assertSame(
            [
                'identificationFileRef',
                'identificationFileName',
                'period',
                'urssafRef',
                'uploader',
                'processingStatus',
                'dates',
                'reportFiles',
                'details',
            ],
            array_keys($info),
        );
        $dump = print_r($submission, true);
        static::assertStringNotContainsString('jane.doe', $dump);
        static::assertStringNotContainsString('user-1', $dump);
    }
}
