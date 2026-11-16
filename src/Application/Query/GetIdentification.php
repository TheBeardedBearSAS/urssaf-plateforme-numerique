<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Reads one identification submission of a tenant at URSSAF (live call, section 2.2).
 */
final readonly class GetIdentification
{
    public function __construct(public TenantId $tenantId, public UrssafReference $reference) {}
}
