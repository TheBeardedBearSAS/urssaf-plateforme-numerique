<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingDates;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Uploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Live read model of a declaration file as reported by URSSAF. It carries the uploader's personal data
 * (ADR 0009): it is never persisted and masked in debug output.
 */
final readonly class DeclarationSubmission
{
    private ?DeclarationType $declarationType;

    /**
     * Public constructor (more than five parameters, as the reference does for its wide value objects).
     *
     * @param list<ReportedPeriod> $periods
     */
    public function __construct(
        private ?string $declarationFileRef,
        private ?string $declarationFileName,
        private ?string $rawDeclarationType,
        private array $periods,
        private ?UrssafReference $urssafRef,
        private Uploader $uploader,
        private ProcessingStatus $processingStatus,
        private PaymentState $paymentState,
        private ProcessingDates $dates,
        private ReportFiles $reportFiles,
        private ?DeclarationStatistics $statistics,
        private ?string $concernedRegularisation,
    ) {
        $this->declarationType = DeclarationType::fromCode($rawDeclarationType);
    }

    public function declarationFileRef(): ?string
    {
        return $this->declarationFileRef;
    }

    public function declarationFileName(): ?string
    {
        return $this->declarationFileName;
    }

    public function rawDeclarationType(): ?string
    {
        return $this->rawDeclarationType;
    }

    /**
     * Null for a missing or unknown type.
     */
    public function declarationType(): ?DeclarationType
    {
        return $this->declarationType;
    }

    /**
     * @return list<ReportedPeriod>
     */
    public function periods(): array
    {
        return $this->periods;
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

    public function paymentState(): PaymentState
    {
        return $this->paymentState;
    }

    public function dates(): ProcessingDates
    {
        return $this->dates;
    }

    public function reportFiles(): ReportFiles
    {
        return $this->reportFiles;
    }

    public function statistics(): ?DeclarationStatistics
    {
        return $this->statistics;
    }

    public function concernedRegularisation(): ?string
    {
        return $this->concernedRegularisation;
    }

    /**
     * Personal data: the uploader is masked in debug output (var_dump, print_r, dumpers).
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'declarationFileRef' => $this->declarationFileRef,
            'declarationFileName' => $this->declarationFileName,
            'rawDeclarationType' => $this->rawDeclarationType,
            'periods' => $this->periods,
            'urssafRef' => $this->urssafRef,
            'uploader' => $this->uploader->__debugInfo(),
            'processingStatus' => $this->processingStatus,
            'paymentState' => $this->paymentState,
            'dates' => $this->dates,
            'reportFiles' => $this->reportFiles,
            'statistics' => $this->statistics,
            'concernedRegularisation' => $this->concernedRegularisation,
        ];
    }
}
