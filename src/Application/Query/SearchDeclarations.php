<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Searches the declaration submissions of a tenant at URSSAF (section 2.7), optionally by declaration type.
 */
final readonly class SearchDeclarations
{
    public function __construct(public TenantId $tenantId, public SubmissionSearch $search) {}
}
