<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingDates;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Uploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Live read model of an identification file as reported by URSSAF. It carries the uploader's personal data
 * (ADR 0009): it is never persisted and masked in debug output.
 */
final readonly class IdentificationSubmission
{
    /**
     * Public constructor (more than five parameters, as the reference does for its wide value objects).
     */
    public function __construct(
        private ?string $identificationFileRef,
        private ?string $identificationFileName,
        private ?ReportedPeriod $period,
        private ?UrssafReference $urssafRef,
        private Uploader $uploader,
        private ProcessingStatus $processingStatus,
        private ProcessingDates $dates,
        private ReportFiles $reportFiles,
        private ?IdentificationDetails $details,
    ) {}


    public function identificationFileRef(): ?string
    {
        return $this->identificationFileRef;
    }

    public function identificationFileName(): ?string
    {
        return $this->identificationFileName;
    }

    public function period(): ?ReportedPeriod
    {
        return $this->period;
    }

    public function urssafRef(): ?UrssafReference
    {
        return $this->urssafRef;
    }

    public function uploader(): Uploader
    {
        return $this->uploader;
    }

    public function processingStatus(): ProcessingStatus
    {
        return $this->processingStatus;
    }

    public function dates(): ProcessingDates
    {
        return $this->dates;
    }

    public function reportFiles(): ReportFiles
    {
        return $this->reportFiles;
    }

    public function details(): ?IdentificationDetails
    {
        return $this->details;
    }

    /**
     * Personal data: the uploader is masked in debug output (var_dump, print_r, dumpers).
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'identificationFileRef' => $this->identificationFileRef,
            'identificationFileName' => $this->identificationFileName,
            'period' => $this->period,
            'urssafRef' => $this->urssafRef,
            'uploader' => $this->uploader->__debugInfo(),
            'processingStatus' => $this->processingStatus,
            'dates' => $this->dates,
            'reportFiles' => $this->reportFiles,
            'details' => $this->details,
        ];
    }
}
