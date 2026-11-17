<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;

/**
 * Thrown by a non-blocking {@see RateLimiter} when no call slot is available.
 */
final class RateLimitExceeded extends RuntimeException implements RetryableException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly int $retryAfterSeconds,
    ) {
        parent::__construct($message);
    }

    public static function forOperation(Operation $operation, int $retryAfterSeconds): self
    {
        $retryAfterSeconds = max(0, $retryAfterSeconds);

        return new self(
            \sprintf(
                'Local rate limit reached for URSSAF operation %s; retry in %d second(s).',
                $operation->value,
                $retryAfterSeconds,
            ),
            $operation,
            $retryAfterSeconds,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
