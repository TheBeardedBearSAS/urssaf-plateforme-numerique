<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;

final class OutcomeUnknownTest extends TestCase
{
    public function testOutcomeUnknownAfterTransportError(): void
    {
        $exception = OutcomeUnknown::afterTransportError(Operation::UploadIdentification, RuntimeException::class);

        static::assertSame('URSSAF operation uploadPlatformIdentification failed with RuntimeException: the upload may have been received.', $exception->getMessage());
        static::assertSame(Operation::UploadIdentification, $exception->operation());
        static::assertTrue($exception->mayHaveReachedUrssaf());
    }

    public function testOutcomeUnknownAfterStatus(): void
    {
        $exception = OutcomeUnknown::afterStatus(Operation::UploadDeclaration, 502);

        static::assertSame('URSSAF operation uploadPlatformDeclaration answered HTTP 502: the upload may have been received.', $exception->getMessage());
        static::assertSame(Operation::UploadDeclaration, $exception->operation());
        static::assertTrue($exception->mayHaveReachedUrssaf());
    }

    public function testOutcomeUnknownUnreadableAnswer(): void
    {
        $exception = OutcomeUnknown::unreadableAnswer(Operation::UploadDeclaration, 'missing urssafRef');

        static::assertSame('URSSAF operation uploadPlatformDeclaration answered an unreadable response (missing urssafRef): the upload may have been received.', $exception->getMessage());
        static::assertTrue($exception->mayHaveReachedUrssaf());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }
}
