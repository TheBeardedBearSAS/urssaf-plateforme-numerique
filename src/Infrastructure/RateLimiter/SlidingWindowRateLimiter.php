<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Non-blocking in-memory rate limiter: at most $maxCalls calls per tenant, all operations together, in any sliding
 * window of $windowSeconds seconds (ADR 0002; URSSAF publishes no quota, the host chooses the figures). Call instants
 * are kept at second precision; when the limit is reached, the retry delay is the time left until the oldest call
 * leaves the window.
 */
final class SlidingWindowRateLimiter implements RateLimiter
{
    /** @var array<string, array<int, int>> calls per second (UNIX timestamp => count) per tenant identifier */
    private array $calls = [];

    /**
     * @throws InvalidValue "rate_limit_max_calls.not_positive" or "rate_limit_window_seconds.not_positive"
     */
    public function __construct(
        private readonly Clock $clock,
        private readonly int $maxCalls,
        private readonly int $windowSeconds,
    ) {
        if ($maxCalls < 1) {
            throw InvalidValue::notPositive('rate_limit_max_calls');
        }
        if ($windowSeconds < 1) {
            throw InvalidValue::notPositive('rate_limit_window_seconds');
        }
    }

    public function acquire(TenantId $tenantId, Operation $operation): void
    {
        $now = $this->clock->now()->getTimestamp();
        $windowStart = $now - $this->windowSeconds;
        $recent = array_filter(
            $this->calls[$tenantId->value()] ?? [],
            static fn(int $instant): bool => $instant > $windowStart,
            \ARRAY_FILTER_USE_KEY,
        );

        if (array_sum($recent) >= $this->maxCalls) {
            throw RateLimitExceeded::forOperation($operation, min($now, ...array_keys($recent)) + $this->windowSeconds - $now);
        }

        $recent[$now] = ($recent[$now] ?? 0) + 1;
        $this->calls[$tenantId->value()] = $recent;
    }
}
