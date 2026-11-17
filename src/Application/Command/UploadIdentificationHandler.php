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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;

/**
 * Uploads an identification file through {@see SubmissionUploader} (ADR 0004). No concurrency guard: the contract only
 * says one file per period "should" be submitted (ADR 0008).
 */
final readonly class UploadIdentificationHandler
{
    public function __construct(private SubmissionUploader $uploader) {}

    /**
     * @throws SubmissionAlreadyRecorded when the external reference is recorded with another file
     * @throws RetryableUrssafFailure    after a temporary failure (the record is moved to "error")
     */
    public function __invoke(UploadIdentification $command): UploadReport
    {
        return $this->uploader->upload(
            $command->tenantId,
            $command->externalReference,
            SubmissionKind::Identification,
            null,
            $command->file,
        );
    }
}
