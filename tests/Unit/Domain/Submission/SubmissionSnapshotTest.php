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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;

final class SubmissionSnapshotTest extends TestCase
{
    public function testGetters(): void
    {
        $periods = [ReportedPeriod::fromWire('2026-01')];
        $files = ReportFiles::of(FileReference::of('file-2', 'report.csv'), null, null, null);
        $counters = ['numberOfError' => DecimalNumber::fromInt(0), 'totalRevenue' => DecimalNumber::fromString('20.152')];
        $lastUpdate = SubmissionFixtures::now();
        $snapshot = new SubmissionSnapshot('ACCEPTED', 'DECLARED', $periods, $files, $counters, $lastUpdate);

        static::assertSame('ACCEPTED', $snapshot->rawStatus());
        static::assertSame(FileStatus::Accepted, $snapshot->status());
        static::assertSame('DECLARED', $snapshot->rawPaymentStatus());
        static::assertSame(PaymentStatus::Declared, $snapshot->paymentStatus());
        static::assertSame($periods, $snapshot->periods());
        static::assertSame($files, $snapshot->reportFiles());
        static::assertSame($counters, $snapshot->counters());
        static::assertSame($lastUpdate, $snapshot->lastUpdateDate());
    }

    public function testUnknownAndMissingValues(): void
    {
        $unknown = SubmissionFixtures::snapshot('SOMETHING_ELSE', 'SOMETHING_ELSE');

        static::assertSame('SOMETHING_ELSE', $unknown->rawStatus());
        static::assertNull($unknown->status());
        static::assertSame('SOMETHING_ELSE', $unknown->rawPaymentStatus());
        static::assertNull($unknown->paymentStatus());

        $missing = SubmissionFixtures::snapshot(null, null);
        static::assertNull($missing->status());
        static::assertNull($missing->paymentStatus());
        static::assertNull($missing->lastUpdateDate());
    }

    /**
     * @return iterable<string, array{?string, ?string, bool}>
     */
    public static function provideSettlement(): iterable
    {
        yield 'accepted and processed' => ['ACCEPTED', 'PROCESSED', true];
        yield 'accepted and declared' => ['ACCEPTED', 'DECLARED', false];
        yield 'accepted, no payment status' => ['ACCEPTED', null, false];
        yield 'rejected and processed' => ['REJECTED', 'PROCESSED', false];
        yield 'in process and processed' => ['IN_PROCESS', 'PROCESSED', false];
        yield 'unknown status and processed' => ['SOMETHING_ELSE', 'PROCESSED', false];
    }

    #[DataProvider('provideSettlement')]
    public function testIsPaymentSettled(?string $status, ?string $paymentStatus, bool $settled): void
    {
        static::assertSame($settled, SubmissionFixtures::snapshot($status, $paymentStatus)->isPaymentSettled());
    }

    public function testSubmissionError(): void
    {
        $error = SubmissionError::of('ERR_CODE', 'A message.');

        static::assertSame('ERR_CODE', $error->code());
        static::assertSame('A message.', $error->message());
        static::assertNull(SubmissionError::of(null, null)->code());
        static::assertNull(SubmissionError::of(null, null)->message());
    }
}
