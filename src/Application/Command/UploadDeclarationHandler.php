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

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\SubmissionAlreadyRecorded;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\SubmissionUploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;

/**
 * Uploads a declaration file through {@see SubmissionUploader} (ADR 0004). Unless the workflow policy disables it, an
 * upload is refused while another declaration of the same type is in process for the tenant (ADR 0008).
 */
final readonly class UploadDeclarationHandler
{
    public function __construct(private SubmissionUploader $uploader) {}

    /**
     * @throws InvariantViolation        "submission.declaration_in_process"
     * @throws SubmissionAlreadyRecorded when the external reference is recorded with another file
     * @throws RetryableUrssafFailure    after a temporary failure (the record is moved to "error")
     */
    public function __invoke(UploadDeclaration $command): UploadReport
    {
        return $this->uploader->upload(
            $command->tenantId,
            $command->externalReference,
            SubmissionKind::Declaration,
            $command->type,
            $command->file,
        );
    }
}
