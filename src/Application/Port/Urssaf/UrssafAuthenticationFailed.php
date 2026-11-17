<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;

/**
 * Token request refused (HTTP 400 / 401 / 403), HTTP 401 after the token refresh and replay, or HTTP 403 (ADR 0002).
 * Credentials or habilitation must be fixed; not retryable. The message never contains credentials nor tokens.
 */
final class UrssafAuthenticationFailed extends RuntimeException implements UrssafException
{
    private function __construct(string $message, private readonly int $httpStatus)
    {
        parent::__construct($message, $httpStatus);
    }

    public static function withStatus(int $httpStatus): self
    {
        return new self(\sprintf('URSSAF authentication or authorisation failed (HTTP %d).', $httpStatus), $httpStatus);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
