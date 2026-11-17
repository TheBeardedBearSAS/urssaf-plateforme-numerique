<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf;

use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;

/**
 * Optional collaborators of the gateway (see {@see UrssafGatewayFactory}), immutable; each one not given keeps its
 * default:
 * - rate limiter: none (URSSAF publishes no quota, ADR 0002); give a {@see RateLimiter} such as the
 *   in-process `SlidingWindowRateLimiter` (configurable calls per window); when several workers call URSSAF, use a
 *   RateLimiter backed by shared storage;
 * - id generator: random UUIDs (the multipart boundary and the optional request id header of ApiConfiguration);
 * - clock: the system clock (UTC);
 * - logger: no logging.
 *
 *     GatewayOptions::defaults()->withLogger($logger)->withRateLimiter($sharedRateLimiter)
 */
final readonly class GatewayOptions
{
    private function __construct(
        private ?RateLimiter $rateLimiter,
        private ?IdGenerator $idGenerator,
        private ?Clock $clock,
        private ?LoggerInterface $logger,
    ) {}

    public static function defaults(): self
    {
        return new self(null, null, null, null);
    }

    public function withRateLimiter(RateLimiter $rateLimiter): self
    {
        return new self($rateLimiter, $this->idGenerator, $this->clock, $this->logger);
    }

    public function withIdGenerator(IdGenerator $idGenerator): self
    {
        return new self($this->rateLimiter, $idGenerator, $this->clock, $this->logger);
    }

    public function withClock(Clock $clock): self
    {
        return new self($this->rateLimiter, $this->idGenerator, $clock, $this->logger);
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return new self($this->rateLimiter, $this->idGenerator, $this->clock, $logger);
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function rateLimiter(): ?RateLimiter
    {
        return $this->rateLimiter;
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function idGenerator(): ?IdGenerator
    {
        return $this->idGenerator;
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function clock(): ?Clock
    {
        return $this->clock;
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function logger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
