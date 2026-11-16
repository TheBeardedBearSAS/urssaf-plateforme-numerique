<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\RateLimiter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\SlidingWindowRateLimiter;

final class SlidingWindowRateLimiterWindowTest extends TestCase
{
    public function testARejectedCallIsNotCounted(): void
    {
        $clock = RateLimiterProbe::clock();
        $limiter = new SlidingWindowRateLimiter($clock, 1, 10);
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::GetNewsFeed);
        $clock->advanceSeconds(5);
        RateLimiterProbe::retryAfterOrNull($limiter, $tenant, Operation::GetNewsFeed);

        $clock->advanceSeconds(5);

        static::assertNull(RateLimiterProbe::retryAfterOrNull($limiter, $tenant, Operation::GetNewsFeed));
    }

    public function testRetryDelayIsTheTimeUntilTheOldestCallLeavesTheWindow(): void
    {
        $clock = RateLimiterProbe::clock();
        $limiter = new SlidingWindowRateLimiter($clock, 3, 60);
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::GetNewsFeed);
        $clock->advanceSeconds(10);
        RateLimiterProbe::fill($limiter, $tenant, 2);

        static::assertSame(50, RateLimiterProbe::retryAfterOrNull($limiter, $tenant, Operation::GetNewsFeed));
    }

    public function testCallsLeaveTheWindowExactlyOneWindowLater(): void
    {
        $clock = RateLimiterProbe::clock();
        $limiter = new SlidingWindowRateLimiter($clock, 2, 30);
        $tenant = TenantId::fromString('tenant-a');
        RateLimiterProbe::fill($limiter, $tenant, 2);

        $clock->advanceSeconds(29);
        static::assertSame(1, RateLimiterProbe::retryAfterOrNull($limiter, $tenant, Operation::GetNewsFeed));

        $clock->advanceSeconds(1);
        static::assertNull(RateLimiterProbe::retryAfterOrNull($limiter, $tenant, Operation::GetNewsFeed));
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'zero calls' => [0, 60, 'rate_limit_max_calls.not_positive'];
        yield 'negative calls' => [-1, 60, 'rate_limit_max_calls.not_positive'];
        yield 'zero window' => [1, 0, 'rate_limit_window_seconds.not_positive'];
        yield 'negative window' => [1, -1, 'rate_limit_window_seconds.not_positive'];
    }

    #[DataProvider('invalidConfigurations')]
    public function testItRejectsANonPositiveLimitOrWindow(int $maxCalls, int $windowSeconds, string $violation): void
    {
        try {
            new SlidingWindowRateLimiter(RateLimiterProbe::clock(), $maxCalls, $windowSeconds);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame($violation, $exception->violationCode());
        }
    }
}
