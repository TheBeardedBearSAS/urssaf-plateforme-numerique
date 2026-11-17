<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Port;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;

final class RateLimitExceededTest extends TestCase
{
    public function testAccessors(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::UploadDeclaration, 12);

        static::assertContains(RetryableException::class, class_implements($exception));
        static::assertSame(Operation::UploadDeclaration, $exception->operation());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertSame('Local rate limit reached for URSSAF operation uploadPlatformDeclaration; retry in 12 second(s).', $exception->getMessage());
    }

    public function testNegativeDelaysAreClamped(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::GetNewsFeed, -3);

        static::assertSame(0, $exception->retryAfterSeconds());
        static::assertStringEndsWith('retry in 0 second(s).', $exception->getMessage());
        static::assertSame(0, RateLimitExceeded::forOperation(Operation::GetNewsFeed, 0)->retryAfterSeconds());
    }
}
