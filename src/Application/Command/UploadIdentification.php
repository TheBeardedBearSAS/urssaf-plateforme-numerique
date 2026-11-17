<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Uploads an identification file of a tenant (section 2.1) under the host reference of the record (ADR 0004).
 */
final readonly class UploadIdentification
{
    private function __construct(
        public TenantId $tenantId,
        public ExternalReference $externalReference,
        public FileSource $file,
    ) {}

    public static function of(TenantId $tenantId, ExternalReference $externalReference, FileSource $file): self
    {
        return new self($tenantId, $externalReference, $file);
    }
}
