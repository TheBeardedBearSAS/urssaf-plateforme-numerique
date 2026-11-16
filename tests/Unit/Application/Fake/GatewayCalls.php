<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * What a {@see FakePlatformGateway} was asked: the calls in order (also written in the journal), the tenant and platform
 * of each one, the searches and the news feed page requests.
 */
final class GatewayCalls
{
    /** @var list<string> */
    private array $calls = [];

    /** @var list<string> */
    private array $targets = [];

    /** @var list<SubmissionSearch> */
    private array $searches = [];

    /** @var list<PageRequest> */
    private array $newsFeedRequests = [];

    public function __construct(private readonly Journal $journal) {}

    public function record(string $call, TenantId $tenantId, PlatformId $platformId): void
    {
        $this->calls[] = $call;
        $this->targets[] = $tenantId->value() . '|' . $platformId->value();
        $this->journal->add($call);
    }

    public function search(SubmissionSearch $search): void
    {
        $this->searches[] = $search;
    }

    public function newsFeed(PageRequest $request): void
    {
        $this->newsFeedRequests[] = $request;
    }

    /**
     * @return list<string> e.g. "upload_declaration:DECLARATION:file.zip"
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<string> "tenant|platform" of each call
     */
    public function targets(): array
    {
        return $this->targets;
    }

    /**
     * @return list<SubmissionSearch> the searches of both operations
     */
    public function searches(): array
    {
        return $this->searches;
    }

    /**
     * @return list<PageRequest>
     */
    public function newsFeedRequests(): array
    {
        return $this->newsFeedRequests;
    }
}
