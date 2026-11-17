<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;

final class ReportFilesTest extends TestCase
{
    public function testFileReference(): void
    {
        $reference = FileReference::of('file-1', 'report.csv');

        static::assertSame('file-1', $reference->fileId());
        static::assertSame('report.csv', $reference->name());
        static::assertNull(FileReference::of('file-1', null)->name());
        static::assertSame(str_repeat('x', 255), FileReference::of(str_repeat('x', 255), null)->fileId());
        static::assertSame(str_repeat('é', 255), FileReference::of(str_repeat('é', 255), null)->fileId());
        static::assertSame(' a b ', FileReference::of(' a b ', ' n ')->fileId());
        static::assertSame(' n ', FileReference::of(' a b ', ' n ')->name());
    }

    public function testFileReferenceRejectsAnEmptyId(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('file_reference'));

        FileReference::of('', null);
    }

    public function testFileReferenceRejectsATooLongId(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLong('file_reference', 255));

        FileReference::of(str_repeat('é', 256), null);
    }

    public function testFileReferenceRejectsASlash(): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('file_reference', 'no "/" character'));

        FileReference::of('a/b', null);
    }

    public function testNone(): void
    {
        $files = ReportFiles::none();

        static::assertNull($files->globalReport());
        static::assertNull($files->detailedReport());
        static::assertNull($files->usersAnomaliesReport());
        static::assertNull($files->affiliatedUsersFile());
        static::assertSame([], $files->all());
    }

    public function testOfExposesEachFileAndKeysThemByWireName(): void
    {
        $global = FileReference::of('g', null);
        $detailed = FileReference::of('d', 'd.csv');
        $anomalies = FileReference::of('a', null);
        $affiliated = FileReference::of('u', null);

        $files = ReportFiles::of($global, $detailed, $anomalies, $affiliated);

        static::assertSame($global, $files->globalReport());
        static::assertSame($detailed, $files->detailedReport());
        static::assertSame($anomalies, $files->usersAnomaliesReport());
        static::assertSame($affiliated, $files->affiliatedUsersFile());
        static::assertSame(
            [
                'globalReportRef' => $global,
                'detailedReportRef' => $detailed,
                'usersAnomaliesReportRef' => $anomalies,
                'affiliatedUsersFileRef' => $affiliated,
            ],
            $files->all(),
        );
    }

    public function testAllOmitsAbsentFiles(): void
    {
        $detailed = FileReference::of('d', null);
        $affiliated = FileReference::of('u', null);

        $files = ReportFiles::of(null, $detailed, null, $affiliated);

        static::assertSame(['detailedReportRef' => $detailed, 'affiliatedUsersFileRef' => $affiliated], $files->all());
    }
}
