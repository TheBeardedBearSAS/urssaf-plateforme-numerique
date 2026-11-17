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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;

/**
 * Uploads a declaration file of a tenant (section 2.5) under the host reference of the record (ADR 0004).
 */
final readonly class UploadDeclaration
{
    private function __construct(
        public TenantId $tenantId,
        public ExternalReference $externalReference,
        public DeclarationType $type,
        public FileSource $file,
    ) {}

    public static function of(
        TenantId $tenantId,
        ExternalReference $externalReference,
        DeclarationType $type,
        FileSource $file,
    ): self {
        return new self($tenantId, $externalReference, $type, $file);
    }
}
