<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Searches the identification submissions of a tenant at URSSAF (section 2.3); the search must not carry a declaration type.
 */
final readonly class SearchIdentifications
{
    public function __construct(public TenantId $tenantId, public SubmissionSearch $search) {}
}
