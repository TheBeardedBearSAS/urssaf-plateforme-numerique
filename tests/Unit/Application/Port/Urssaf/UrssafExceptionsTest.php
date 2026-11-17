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
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;

final class UrssafExceptionsTest extends TestCase
{
    public function testRequestRejected(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'm', 'd'), ApiError::of('ERR_B', 'm', 'd'));

        $exception = UrssafRequestRejected::withErrors(Operation::UploadDeclaration, 400, $errors);

        static::assertSame('URSSAF operation uploadPlatformDeclaration rejected the request (HTTP 400): ERR_A, ERR_B.', $exception->getMessage());
        static::assertSame(Operation::UploadDeclaration, $exception->operation());
        static::assertSame(400, $exception->httpStatus());
        static::assertSame(400, $exception->getCode());
        static::assertSame($errors, $exception->errors());
        static::assertTrue($exception->hasErrorCode('ERR_B'));
        static::assertFalse($exception->hasErrorCode('ERR_C'));
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testRequestRejectedWithoutErrors(): void
    {
        $exception = UrssafRequestRejected::withErrors(Operation::UploadIdentification, 400, ApiErrors::none());

        static::assertSame('URSSAF operation uploadPlatformIdentification rejected the request (HTTP 400): no error code.', $exception->getMessage());
    }

    public function testResourceNotFound(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'm', 'd'));

        $exception = UrssafResourceNotFound::withErrors(Operation::GetIdentification, $errors);

        static::assertSame('URSSAF operation getPlatformIdentification found no such resource (HTTP 404).', $exception->getMessage());
        static::assertSame(404, $exception->getCode());
        static::assertSame(Operation::GetIdentification, $exception->operation());
        static::assertSame($errors, $exception->errors());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testAuthenticationFailed(): void
    {
        $exception = UrssafAuthenticationFailed::withStatus(401);

        static::assertSame('URSSAF authentication or authorisation failed (HTTP 401).', $exception->getMessage());
        static::assertSame(401, $exception->httpStatus());
        static::assertSame(401, $exception->getCode());
    }
}
