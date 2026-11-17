<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Service;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\SnapshotFactory;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\DeclarationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\IdentificationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

final class SnapshotFactoryTest extends TestCase
{
    public function testItReducesAnIdentification(): void
    {
        $snapshot = SnapshotFactory::fromIdentification(IdentificationMapper::map(
            WireFixtures::fixture('identification_response_handbuilt'),
            new DateTimeZone('UTC'),
        ));

        static::assertSame('ACCEPTED', $snapshot->rawStatus());
        static::assertSame(FileStatus::Accepted, $snapshot->status());
        static::assertNull($snapshot->rawPaymentStatus());
        static::assertCount(1, $snapshot->periods());
        static::assertSame('2025-05', $snapshot->periods()[0]->raw());
        static::assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $snapshot->reportFiles()->globalReport()?->fileId());
        static::assertSame(
            ['numberOfDeclaredUser' => '12', 'numberOfIdentifiedUser' => '10', 'numberOfError' => '2'],
            array_map(static fn(DecimalNumber $counter): string => $counter->toString(), $snapshot->counters()),
        );
        static::assertSame('2025-04-28T20:04:14Z', $snapshot->lastUpdateDate()?->format());
    }

    public function testAnIdentificationWithoutPeriodNorDetails(): void
    {
        $snapshot = SnapshotFactory::fromIdentification(IdentificationMapper::map(
            WireFixtures::payload(['status' => 'IN_PROCESS']),
            new DateTimeZone('UTC'),
        ));

        static::assertSame('IN_PROCESS', $snapshot->rawStatus());
        static::assertSame([], $snapshot->periods());
        static::assertSame([], $snapshot->counters());
        static::assertNull($snapshot->lastUpdateDate());
    }

    public function testItReducesADeclaration(): void
    {
        $snapshot = SnapshotFactory::fromDeclaration(DeclarationMapper::map(
            WireFixtures::fixture('declaration_response_handbuilt'),
            new DateTimeZone('UTC'),
        ));

        static::assertSame('ACCEPTED', $snapshot->rawStatus());
        static::assertSame('PROCESSED', $snapshot->rawPaymentStatus());
        static::assertSame(PaymentStatus::Processed, $snapshot->paymentStatus());
        static::assertTrue($snapshot->isPaymentSettled());
        static::assertSame(['2025-04', '2025-05'], array_map(static fn(ReportedPeriod $period): string => $period->raw(), $snapshot->periods()));
        static::assertSame('c3d4e5f6-a7b8-9012-cdef-123456789012', $snapshot->reportFiles()->usersAnomaliesReport()?->fileId());
        static::assertSame(
            [
                'numberOfDeclaredUser' => '12',
                'totalRevenue' => '15230.5',
                'debitAmount' => '3350.71',
                'paymentAmount' => '3350.71',
                'numberOfError' => '0',
            ],
            array_map(static fn(DecimalNumber $counter): string => $counter->toString(), $snapshot->counters()),
        );
        static::assertSame('2025-04-28T20:04:14Z', $snapshot->lastUpdateDate()?->format());
    }

    public function testADeclarationWithoutStatistics(): void
    {
        $snapshot = SnapshotFactory::fromDeclaration(DeclarationMapper::map(WireFixtures::payload([]), new DateTimeZone('UTC')));

        static::assertNull($snapshot->rawStatus());
        static::assertNull($snapshot->rawPaymentStatus());
        static::assertSame([], $snapshot->periods());
        static::assertSame([], $snapshot->counters());
    }

    public function testOnlyThePresentCountersAreKept(): void
    {
        $snapshot = SnapshotFactory::fromDeclaration(DeclarationMapper::map(
            WireFixtures::payload(['statistics' => ['debitAmount' => 10, 'paymentAmount' => null]]),
            new DateTimeZone('UTC'),
        ));

        static::assertSame(['debitAmount'], array_keys($snapshot->counters()));
    }

    public function testNoUserDataReachesTheSnapshot(): void
    {
        $snapshot = SnapshotFactory::fromDeclaration(DeclarationMapper::map(
            WireFixtures::fixture('declaration_response_handbuilt'),
            new DateTimeZone('UTC'),
        ));

        static::assertStringNotContainsString('uploader@example.com', serialize($snapshot));
        static::assertStringNotContainsString('user-0001', serialize($snapshot));
    }
}
