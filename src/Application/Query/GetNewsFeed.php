<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Reads a page of the news feed of the platform of a tenant (not available on the test API).
 */
final readonly class GetNewsFeed
{
    public function __construct(public TenantId $tenantId, public PageRequest $page) {}
}
