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
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;

final class UrssafUnavailableTest extends TestCase
{
    public function testUnavailableWithStatus(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'm', 'd'));

        $exception = UrssafUnavailable::withStatus(Operation::GetDeclaration, 429, 30, $errors);

        static::assertContains(RetryableException::class, class_implements($exception));
        static::assertSame('URSSAF operation getPlatformDeclaration is temporarily unavailable (HTTP 429).', $exception->getMessage());
        static::assertSame(Operation::GetDeclaration, $exception->operation());
        static::assertSame(429, $exception->httpStatus());
        static::assertSame(429, $exception->getCode());
        static::assertSame(30, $exception->retryAfterSeconds());
        static::assertTrue($exception->isQuotaExceeded());
        static::assertNull($exception->causeClass());
        static::assertSame($errors, $exception->errors());
        static::assertFalse($exception->mayHaveReachedUrssaf());
    }

    public function testUnavailableStatusClassification(): void
    {
        $serverError = UrssafUnavailable::withStatus(Operation::GetDeclaration, 500, null, ApiErrors::none());

        static::assertFalse($serverError->isQuotaExceeded());
        static::assertNull($serverError->retryAfterSeconds());
        static::assertTrue($serverError->errors()->isEmpty());
        static::assertTrue($serverError->mayHaveReachedUrssaf());
        static::assertFalse(UrssafUnavailable::withStatus(Operation::GetDeclaration, 499, null, ApiErrors::none())->mayHaveReachedUrssaf());
        static::assertFalse(UrssafUnavailable::withStatus(Operation::GetDeclaration, 428, null, ApiErrors::none())->isQuotaExceeded());
        static::assertFalse(UrssafUnavailable::withStatus(Operation::GetDeclaration, 430, null, ApiErrors::none())->isQuotaExceeded());
    }

    public function testUnavailableDueToTransportError(): void
    {
        $exception = UrssafUnavailable::dueToTransportError(Operation::SearchDeclarations, RuntimeException::class);

        static::assertSame('URSSAF operation searchPlatformDeclaration could not be reached (RuntimeException).', $exception->getMessage());
        static::assertSame(Operation::SearchDeclarations, $exception->operation());
        static::assertNull($exception->httpStatus());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->retryAfterSeconds());
        static::assertFalse($exception->isQuotaExceeded());
        static::assertSame(RuntimeException::class, $exception->causeClass());
        static::assertNull($exception->getPrevious());
        static::assertTrue($exception->errors()->isEmpty());
        static::assertTrue($exception->mayHaveReachedUrssaf());
    }

    public function testUnavailableWhileAuthenticating(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'm', 'd'));

        $exception = UrssafUnavailable::whileAuthenticating(Operation::UploadDeclaration, 503, 12, null, $errors);

        static::assertSame('URSSAF authentication for operation uploadPlatformDeclaration is temporarily unavailable.', $exception->getMessage());
        static::assertSame(Operation::UploadDeclaration, $exception->operation());
        static::assertSame(503, $exception->httpStatus());
        static::assertSame(503, $exception->getCode());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertNull($exception->causeClass());
        static::assertSame($errors, $exception->errors());
        static::assertFalse($exception->mayHaveReachedUrssaf());

        $transport = UrssafUnavailable::whileAuthenticating(Operation::UploadDeclaration, null, null, RuntimeException::class, ApiErrors::none());
        static::assertSame(0, $transport->getCode());
        static::assertSame(RuntimeException::class, $transport->causeClass());
        static::assertTrue($transport->errors()->isEmpty());
        static::assertFalse($transport->mayHaveReachedUrssaf());
    }
}
