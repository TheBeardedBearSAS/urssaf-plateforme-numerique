<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;

/**
 * Reads one local submission of a tenant (no call to URSSAF).
 */
final readonly class GetSubmission
{
    public function __construct(public TenantId $tenantId, public SubmissionId $submissionId) {}
}
