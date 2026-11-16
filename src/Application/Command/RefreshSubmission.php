<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;

/**
 * Polls one local submission known to URSSAF now, whatever its polling schedule (ADR 0004).
 */
final readonly class RefreshSubmission
{
    private function __construct(public TenantId $tenantId, public SubmissionId $submissionId) {}

    public static function of(TenantId $tenantId, SubmissionId $submissionId): self
    {
        return new self($tenantId, $submissionId);
    }
}
