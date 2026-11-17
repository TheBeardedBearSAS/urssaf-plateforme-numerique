<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\AdjustableClock;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;

final class GatewayOptionsTest extends TestCase
{
    public function testTheDefaultsGiveNothing(): void
    {
        $options = GatewayOptions::defaults();

        static::assertNull($options->rateLimiter());
        static::assertNull($options->idGenerator());
        static::assertNull($options->clock());
        static::assertNull($options->logger());
    }

    public function testEachWitherSetsOnlyItsOwnCollaboratorAndKeepsTheOthers(): void
    {
        $rateLimiter = new RecordingRateLimiter();
        $ids = new SequenceIdGenerator();
        $clock = new AdjustableClock(new DateTimeImmutable('2024-01-01T10:00:00Z'));
        $logger = new CapturingLogger();

        $options = GatewayOptions::defaults()
            ->withRateLimiter($rateLimiter)
            ->withIdGenerator($ids)
            ->withClock($clock)
            ->withLogger($logger);

        static::assertSame($rateLimiter, $options->rateLimiter());
        static::assertSame($ids, $options->idGenerator());
        static::assertSame($clock, $options->clock());
        static::assertSame($logger, $options->logger());
    }

    public function testEachWitherKeepsWhatWasAlreadyGiven(): void
    {
        $rateLimiter = new RecordingRateLimiter();
        $ids = new SequenceIdGenerator();
        $clock = new AdjustableClock(new DateTimeImmutable('2024-01-01T10:00:00Z'));
        $logger = new CapturingLogger();

        $reversed = GatewayOptions::defaults()
            ->withLogger($logger)
            ->withClock($clock)
            ->withIdGenerator($ids)
            ->withRateLimiter($rateLimiter);

        static::assertSame($rateLimiter, $reversed->rateLimiter());
        static::assertSame($ids, $reversed->idGenerator());
        static::assertSame($clock, $reversed->clock());
        static::assertSame($logger, $reversed->logger());
    }

    public function testAWitherDoesNotChangeTheInstanceItIsCalledOn(): void
    {
        $options = GatewayOptions::defaults();

        $options->withLogger(new CapturingLogger());

        static::assertNull($options->logger());
    }
}
