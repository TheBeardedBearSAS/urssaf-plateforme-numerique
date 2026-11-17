<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;
use Throwable;

/**
 * Logs the failures that happen after a commit. Only class names and the failure message are logged: event payloads may
 * hold personal data.
 */
final readonly class PsrLoggerFailureReporter implements FailureReporter
{
    public function __construct(private LoggerInterface $logger = new NullLogger()) {}

    public function eventPublicationFailed(Throwable $failure, array $events): void
    {
        $this->logger->error('Domain event publication failed after commit.', [
            'exception' => $failure::class,
            'message' => $failure->getMessage(),
            'events' => array_map(static fn(DomainEvent $event): string => $event::class, $events),
        ]);
    }
}
