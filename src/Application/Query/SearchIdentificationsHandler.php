<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;

final readonly class SearchIdentificationsHandler
{
    public function __construct(private PlatformGateway $gateway, private PlatformResolver $platforms) {}

    /**
     * @return Page<IdentificationSubmission>
     *
     * @throws InvalidValue "submission_search.type_not_supported" when the search carries a declaration type (the
     *                      identification search has no such parameter, section 2.3)
     */
    public function __invoke(SearchIdentifications $query): Page
    {
        if ($query->search->type() instanceof DeclarationType) {
            throw InvalidValue::because(
                'submission_search.type_not_supported',
                'Invalid submission_search: the identification search does not accept a declaration type.',
            );
        }

        return $this->gateway->searchIdentifications(
            $query->tenantId,
            $this->platforms->forTenant($query->tenantId),
            $query->search,
        );
    }
}
