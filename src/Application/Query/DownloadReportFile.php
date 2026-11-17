<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Downloads a report file of an identification or a declaration submission from URSSAF (live call).
 */
final readonly class DownloadReportFile
{
    private function __construct(
        public TenantId $tenantId,
        public SubmissionKind $kind,
        public UrssafReference $reference,
        public FileReference $file,
    ) {}

    public static function identification(TenantId $tenantId, UrssafReference $reference, FileReference $file): self
    {
        return new self($tenantId, SubmissionKind::Identification, $reference, $file);
    }

    public static function declaration(TenantId $tenantId, UrssafReference $reference, FileReference $file): self
    {
        return new self($tenantId, SubmissionKind::Declaration, $reference, $file);
    }
}
