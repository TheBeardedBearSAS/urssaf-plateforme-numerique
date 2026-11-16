<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;

final readonly class GetNewsFeedHandler
{
    public function __construct(private PlatformGateway $gateway, private PlatformResolver $platforms) {}

    /**
     * @return Page<NewsItem>
     *
     * @throws OperationNotSupported on the test API
     */
    public function __invoke(GetNewsFeed $query): Page
    {
        return $this->gateway->newsFeed($query->tenantId, $this->platforms->forTenant($query->tenantId), $query->page);
    }
}
