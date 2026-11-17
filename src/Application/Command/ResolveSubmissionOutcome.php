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

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Operator decision on a submission whose upload outcome is unknown (ADR 0003): URSSAF received it (with its urssafRef)
 * or did not.
 */
final readonly class ResolveSubmissionOutcome
{
    /**
     * @param ?UrssafReference $urssafReference null when the upload was not received
     */
    private function __construct(
        public TenantId $tenantId,
        public SubmissionId $submissionId,
        public ?UrssafReference $urssafReference,
    ) {}

    /**
     * URSSAF holds the upload under this reference: the submission moves to in_process and is polled.
     */
    public static function received(TenantId $tenantId, SubmissionId $submissionId, UrssafReference $urssafReference): self
    {
        return new self($tenantId, $submissionId, $urssafReference);
    }

    /**
     * The upload never reached URSSAF: the submission moves to not_received and the host may upload it again.
     */
    public static function notReceived(TenantId $tenantId, SubmissionId $submissionId): self
    {
        return new self($tenantId, $submissionId, null);
    }
}
