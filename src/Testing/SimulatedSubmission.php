<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Testing;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * A file uploaded to the {@see SimulatedPlatformGateway}: each read of the submission moves its processing and payment
 * statuses to the next step of their sequences, the last step is repeated.
 *
 * @internal
 */
final class SimulatedSubmission
{
    private FileStatus $status;

    private PaymentStatus $paymentStatus;

    private Instant $updatedAt;

    /**
     * @param non-empty-list<string>           $periods  withholding periods ("YYYY-MM")
     * @param SimulatedSequence<FileStatus>    $statuses
     * @param SimulatedSequence<PaymentStatus> $payments
     */
    public function __construct(
        public readonly TenantId $tenantId,
        public readonly PlatformId $platformId,
        public readonly SubmissionKind $kind,
        public readonly UrssafReference $reference,
        public readonly FileName $fileName,
        public readonly ?DeclarationType $type,
        public readonly array $periods,
        public readonly Instant $uploadedAt,
        private readonly SimulatedSequence $statuses,
        private readonly SimulatedSequence $payments,
    ) {
        $this->status = $statuses->head();
        $this->paymentStatus = $payments->head();
        $this->updatedAt = $uploadedAt;
    }

    public function isOf(TenantId $tenantId, PlatformId $platformId, SubmissionKind $kind): bool
    {
        return $this->kind === $kind && $this->tenantId->equals($tenantId) && $this->platformId->equals($platformId);
    }

    /**
     * Moves both statuses to their next step; the statuses read afterwards are those of this step.
     */
    public function poll(Instant $now): void
    {
        $this->status = $this->statuses->next();
        $this->paymentStatus = $this->payments->next();
        $this->updatedAt = $now;
    }

    public function status(): FileStatus
    {
        return $this->status;
    }

    public function paymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }
}
