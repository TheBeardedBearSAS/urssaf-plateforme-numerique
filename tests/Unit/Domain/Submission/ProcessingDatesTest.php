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

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ApiTimestamp;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingDates;

final class ProcessingDatesTest extends TestCase
{
    public function testGetters(): void
    {
        $zone = new DateTimeZone('UTC');
        $last = Instant::fromIso8601('2022-02-01T16:40:00Z');
        $upload = ApiTimestamp::of('2022-02-01T16:00:00', $zone);
        $treatment = ApiTimestamp::of('not a date', $zone);

        $dates = ProcessingDates::of($last, $upload, $treatment);

        static::assertSame($last, $dates->lastUpdateDate());
        static::assertSame($upload, $dates->uploadDate());
        static::assertSame($treatment, $dates->treatmentDate());
    }

    public function testAllAbsent(): void
    {
        $dates = ProcessingDates::of(null, null, null);

        static::assertNull($dates->lastUpdateDate());
        static::assertNull($dates->uploadDate());
        static::assertNull($dates->treatmentDate());
    }
}
