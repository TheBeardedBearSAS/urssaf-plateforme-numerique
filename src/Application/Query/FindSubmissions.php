<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * Lists the local submissions of a tenant, the most recent first, optionally in one state (no call to URSSAF). A null limit means the default page size of the policy.
 */
final readonly class FindSubmissions
{
    public function __construct(public TenantId $tenantId, public ?SubmissionState $state, public ?int $limit, public int $offset) {}
}
