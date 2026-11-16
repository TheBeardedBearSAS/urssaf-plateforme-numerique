<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;

final readonly class SearchDeclarationsHandler
{
    public function __construct(private PlatformGateway $gateway, private PlatformResolver $platforms) {}

    /**
     * @return Page<DeclarationSubmission>
     */
    public function __invoke(SearchDeclarations $query): Page
    {
        return $this->gateway->searchDeclarations(
            $query->tenantId,
            $this->platforms->forTenant($query->tenantId),
            $query->search,
        );
    }
}
