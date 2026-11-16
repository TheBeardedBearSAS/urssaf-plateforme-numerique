<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Policy;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PollingWindow;

/**
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class WorkflowPolicyTest extends TestCase
{
    public function testDefaults(): void
    {
        $policy = new WorkflowPolicy();

        static::assertSame(5, $policy->maxUploadAttempts());
        static::assertEquals(new DateInterval('PT1H'), $policy->staleUploadAfter());
        static::assertEquals(new DateInterval('P1D'), $policy->manualReviewAfter());
        static::assertSame(1, $policy->reconciliationMaxPages());
        static::assertSame(500, $policy->reconciliationPageSize());
        static::assertSame(PageRequest::MAX_SIZE, $policy->reconciliationPageSize());
        static::assertTrue($policy->concurrentDeclarationGuard());
        static::assertEquals(new DateInterval('P30D'), $policy->outboxRetention());
        static::assertSame(100, $policy->pageSize(null));
        static::assertSame(1, $policy->pageSize(1));
        static::assertSame(1000, $policy->pageSize(1000));
        static::assertSame(1000, $policy->pageSize(1001));
    }

    public function testThePollingDefaultsAreThoseOfThePollingWindow(): void
    {
        $policy = new WorkflowPolicy();
        $defaults = PollingWindow::defaults();

        static::assertEquals($defaults, $policy->pollingWindow());
        static::assertEquals($defaults->statusPollInterval(), $policy->pollingWindow()->statusPollInterval());
        static::assertEquals($defaults->paymentPollInterval(), $policy->pollingWindow()->paymentPollInterval());
        static::assertEquals($defaults->maxPollingAge(), $policy->pollingWindow()->maxPollingAge());
        static::assertEquals(new DateInterval('PT10M'), $policy->pollingWindow()->statusPollInterval());
        static::assertEquals(new DateInterval('PT6H'), $policy->pollingWindow()->paymentPollInterval());
        static::assertEquals(new DateInterval('P1Y'), $policy->pollingWindow()->maxPollingAge());
    }

    public function testCustomValues(): void
    {
        $policy = new WorkflowPolicy(
            new DateInterval('PT1M'),
            new DateInterval('PT2M'),
            new DateInterval('P3D'),
            2,
            new DateInterval('PT4M'),
            new DateInterval('PT5M'),
            3,
            false,
            new DateInterval('P6D'),
            7,
            8,
        );

        static::assertEquals(new DateInterval('PT1M'), $policy->pollingWindow()->statusPollInterval());
        static::assertEquals(new DateInterval('PT2M'), $policy->pollingWindow()->paymentPollInterval());
        static::assertEquals(new DateInterval('P3D'), $policy->pollingWindow()->maxPollingAge());
        static::assertSame(2, $policy->maxUploadAttempts());
        static::assertEquals(new DateInterval('PT4M'), $policy->staleUploadAfter());
        static::assertEquals(new DateInterval('PT5M'), $policy->manualReviewAfter());
        static::assertSame(3, $policy->reconciliationMaxPages());
        static::assertFalse($policy->concurrentDeclarationGuard());
        static::assertEquals(new DateInterval('P6D'), $policy->outboxRetention());
        static::assertSame(7, $policy->pageSize(null));
        static::assertSame(8, $policy->pageSize(9));
        static::assertSame(4, $policy->pageSize(4));
    }

    public function testTheSmallestValuesAreAccepted(): void
    {
        $policy = new WorkflowPolicy(
            new DateInterval('PT0S'),
            new DateInterval('PT0S'),
            new DateInterval('PT0S'),
            1,
            new DateInterval('PT1S'),
            new DateInterval('PT0S'),
            1,
            true,
            new DateInterval('PT0S'),
            1,
            1,
        );

        static::assertSame(1, $policy->maxUploadAttempts());
        static::assertSame(1, $policy->reconciliationMaxPages());
        static::assertSame(1, $policy->pageSize(null));
        static::assertSame(1, $policy->pageSize(2));
    }

    public function testWithoutTheGuardEverythingElseIsKept(): void
    {
        $policy = new WorkflowPolicy(
            new DateInterval('PT1M'),
            new DateInterval('PT2M'),
            new DateInterval('P3D'),
            2,
            new DateInterval('PT4M'),
            new DateInterval('PT5M'),
            3,
            true,
            new DateInterval('P6D'),
            7,
            8,
        );

        $relaxed = $policy->withoutConcurrentDeclarationGuard();

        static::assertTrue($policy->concurrentDeclarationGuard());
        static::assertFalse($relaxed->concurrentDeclarationGuard());
        static::assertEquals($policy->pollingWindow(), $relaxed->pollingWindow());
        static::assertSame(2, $relaxed->maxUploadAttempts());
        static::assertEquals($policy->staleUploadAfter(), $relaxed->staleUploadAfter());
        static::assertEquals($policy->manualReviewAfter(), $relaxed->manualReviewAfter());
        static::assertSame(3, $relaxed->reconciliationMaxPages());
        static::assertEquals($policy->outboxRetention(), $relaxed->outboxRetention());
        static::assertSame(7, $relaxed->pageSize(null));
        static::assertSame(8, $relaxed->pageSize(100));
    }

    public function testTheIntervalsAreCopiedOnTheWayInAndOut(): void
    {
        $stale = new DateInterval('PT1H');
        $review = new DateInterval('P1D');
        $retention = new DateInterval('P30D');
        $policy = new WorkflowPolicy(
            new DateInterval('PT10M'),
            new DateInterval('PT6H'),
            new DateInterval('P1Y'),
            5,
            $stale,
            $review,
            1,
            true,
            $retention,
            100,
            1000,
        );

        $this->reinitialise($stale);
        $this->reinitialise($review);
        $this->reinitialise($retention);

        static::assertSame(1, $policy->staleUploadAfter()->h);
        static::assertSame(1, $policy->manualReviewAfter()->d);
        static::assertSame(30, $policy->outboxRetention()->d);
        static::assertNotSame($stale, $policy->staleUploadAfter());
        static::assertNotSame($review, $policy->manualReviewAfter());
        static::assertNotSame($retention, $policy->outboxRetention());
        static::assertNotSame($policy->staleUploadAfter(), $policy->staleUploadAfter());
        static::assertNotSame($policy->manualReviewAfter(), $policy->manualReviewAfter());
        static::assertNotSame($policy->outboxRetention(), $policy->outboxRetention());
    }

    /**
     * @return iterable<string, array{string, int, int, int, int}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'no upload attempt' => ['workflow_policy.max_upload_attempts.not_positive', 0, 1, 100, 1000];
        yield 'no reconciliation page' => ['workflow_policy.reconciliation_max_pages.not_positive', 5, 0, 100, 1000];
        yield 'no default page size' => ['workflow_policy.default_page_size.not_positive', 5, 1, 0, 5];
        yield 'no maximum page size' => ['workflow_policy.max_page_size.not_positive', 5, 1, 1, 0];
    }

    #[DataProvider('invalidSettings')]
    public function testRefusesAnInvalidSetting(string $code, int $attempts, int $pages, int $defaultPageSize, int $maxPageSize): void
    {
        try {
            self::policy($attempts, $pages, $defaultPageSize, $maxPageSize);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testRefusesADefaultPageSizeAboveTheMaximum(): void
    {
        try {
            self::policy(5, 1, 11, 10);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame('workflow_policy.default_page_size_above_max', $exception->violationCode());
            static::assertSame(['default_page_size' => 11, 'max_page_size' => 10], $exception->params());
            static::assertSame(
                'Invalid workflow_policy: the default page size must not be greater than the maximum page size.',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function negativeIntervals(): iterable
    {
        yield 'status poll interval' => ['polling_window.negative', 0];
        yield 'payment poll interval' => ['polling_window.negative', 1];
        yield 'maximum polling age' => ['polling_window.negative', 2];
        yield 'stale upload' => ['workflow_policy.negative_interval', 3];
        yield 'manual review' => ['workflow_policy.negative_interval', 4];
        yield 'outbox retention' => ['workflow_policy.negative_interval', 5];
    }

    #[DataProvider('negativeIntervals')]
    public function testRefusesANegativeInterval(string $code, int $position): void
    {
        $negative = self::negative();
        $intervals = [new DateInterval('PT10M'), new DateInterval('PT6H'), new DateInterval('P1Y'), new DateInterval('PT1H'), new DateInterval('P1D'), new DateInterval('P30D')];
        $intervals[$position] = $negative;

        try {
            new WorkflowPolicy($intervals[0], $intervals[1], $intervals[2], 5, $intervals[3], $intervals[4], 1, true, $intervals[5], 100, 1000);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testRefusesAZeroStaleUploadInterval(): void
    {
        try {
            new WorkflowPolicy(
                new DateInterval('PT10M'),
                new DateInterval('PT6H'),
                new DateInterval('P1Y'),
                5,
                new DateInterval('PT0S'),
            );
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame('workflow_policy.stale_upload_after.not_positive', $exception->violationCode());
        }
    }

    public function testTheNegativeIntervalNamesTheSetting(): void
    {
        $negative = self::negative();

        try {
            new WorkflowPolicy(
                new DateInterval('PT10M'),
                new DateInterval('PT6H'),
                new DateInterval('P1Y'),
                5,
                new DateInterval('PT1H'),
                new DateInterval('P1D'),
                1,
                true,
                $negative,
                100,
                1000,
            );
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame(['setting' => 'outbox_retention'], $exception->params());
            static::assertSame('Invalid workflow_policy: intervals must not be negative.', $exception->getMessage());
        }
    }

    public function testRefusesANonPositiveLimit(): void
    {
        try {
            (new WorkflowPolicy())->pageSize(0);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame('workflow_policy.limit.not_positive', $exception->violationCode());
        }
    }

    private static function policy(int $maxUploadAttempts, int $reconciliationMaxPages, int $defaultPageSize, int $maxPageSize): WorkflowPolicy
    {
        return new WorkflowPolicy(
            new DateInterval('PT10M'),
            new DateInterval('PT6H'),
            new DateInterval('P1Y'),
            $maxUploadAttempts,
            new DateInterval('PT1H'),
            new DateInterval('P1D'),
            $reconciliationMaxPages,
            true,
            new DateInterval('P30D'),
            $defaultPageSize,
            $maxPageSize,
        );
    }

    private static function negative(): DateInterval
    {
        return (new DateTimeImmutable('2026-01-01'))->diff(new DateTimeImmutable('2025-12-31'));
    }

    private function reinitialise(DateInterval $interval): void
    {
        \call_user_func([$interval, '__construct'], 'PT9M');
    }
}
