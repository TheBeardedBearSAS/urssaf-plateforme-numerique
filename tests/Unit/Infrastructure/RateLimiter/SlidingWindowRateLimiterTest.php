<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\RateLimiter;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\SlidingWindowRateLimiter;

final class SlidingWindowRateLimiterTest extends TestCase
{
    public function testAllowsCallsUpToTheConfiguredLimit(): void
    {
        $limiter = new SlidingWindowRateLimiter(RateLimiterProbe::clock(), 3, 60);
        $tenant = TenantId::fromString('tenant-a');

        for ($i = 0; $i < 3; ++$i) {
            static::assertNull(RateLimiterProbe::retryAfterOrNull($limiter, $tenant, Operation::GetNewsFeed));
        }
    }

    public function testRejectsTheCallAboveTheLimit(): void
    {
        $limiter = new SlidingWindowRateLimiter(RateLimiterProbe::clock(), 3, 60);
        $tenant = TenantId::fromString('tenant-a');
        RateLimiterProbe::fill($limiter, $tenant, 3);

        $this->expectExceptionObject(RateLimitExceeded::forOperation(Operation::GetNewsFeed, 60));

        $limiter->acquire($tenant, Operation::GetNewsFeed);
    }

    public function testExceptionNamesTheOperation(): void
    {
        $limiter = new SlidingWindowRateLimiter(RateLimiterProbe::clock(), 1, 60);
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::UploadDeclaration);

        try {
            $limiter->acquire($tenant, Operation::GetDeclaration);
            static::fail('The limit should be exceeded.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(Operation::GetDeclaration, $exception->operation());
        }
    }

    public function testTheLimitIsCountedPerTenantAndAllOperationsTogether(): void
    {
        $limiter = new SlidingWindowRateLimiter(RateLimiterProbe::clock(), 2, 60);
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::UploadIdentification);
        $limiter->acquire($tenant, Operation::SearchDeclarations);

        static::assertSame(60, RateLimiterProbe::retryAfterOrNull($limiter, $tenant, Operation::GetNewsFeed));
        static::assertNull(RateLimiterProbe::retryAfterOrNull($limiter, TenantId::fromString('tenant-b'), Operation::GetNewsFeed));
    }

    public function testASingleCallPerWindowIsEnforced(): void
    {
        $limiter = new SlidingWindowRateLimiter(RateLimiterProbe::clock(), 1, 1);
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::GetNewsFeed);

        static::assertSame(1, RateLimiterProbe::retryAfterOrNull($limiter, $tenant, Operation::GetNewsFeed));
    }
}
