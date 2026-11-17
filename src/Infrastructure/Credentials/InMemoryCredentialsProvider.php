<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Credentials;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Credentials provider backed by a map of tenant identifier to credentials (configuration or tests).
 */
final readonly class InMemoryCredentialsProvider implements CredentialsProvider
{
    /**
     * @param array<string, Credentials> $credentialsByTenant keyed by tenant identifier
     */
    public function __construct(private array $credentialsByTenant) {}

    /**
     * @throws RecordNotFound "credentials.not_found"
     */
    public function forTenant(TenantId $tenantId): Credentials
    {
        return $this->credentialsByTenant[$tenantId->value()]
            ?? throw RecordNotFound::withIdentifier('credentials', $tenantId->value());
    }
}
