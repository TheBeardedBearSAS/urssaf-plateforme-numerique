<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PollingWindow;

final class PollingWindowTest extends TestCase
{
    public function testDefaults(): void
    {
        $window = PollingWindow::defaults();

        static::assertSame('0-0-0 0:10:0', $window->statusPollInterval()->format('%y-%m-%d %h:%i:%s'));
        static::assertSame('0-0-0 6:0:0', $window->paymentPollInterval()->format('%y-%m-%d %h:%i:%s'));
        static::assertSame('1-0-0 0:0:0', $window->maxPollingAge()->format('%y-%m-%d %h:%i:%s'));
    }

    public function testOf(): void
    {
        $status = new DateInterval('PT1M');
        $payment = new DateInterval('PT2M');
        $maxAge = new DateInterval('PT3M');
        $window = PollingWindow::of($status, $payment, $maxAge);

        static::assertEquals($status, $window->statusPollInterval());
        static::assertEquals($payment, $window->paymentPollInterval());
        static::assertEquals($maxAge, $window->maxPollingAge());
    }

    public function testIntervalsAreCopiedOnTheWayInAndOut(): void
    {
        $status = new DateInterval('PT1M');
        $payment = new DateInterval('PT2M');
        $maxAge = new DateInterval('PT3M');
        $window = PollingWindow::of($status, $payment, $maxAge);

        $this->reinitialise($status);
        $this->reinitialise($payment);
        $this->reinitialise($maxAge);

        static::assertSame(1, $window->statusPollInterval()->i);
        static::assertSame(2, $window->paymentPollInterval()->i);
        static::assertSame(3, $window->maxPollingAge()->i);
        static::assertNotSame($status, $window->statusPollInterval());
        static::assertNotSame($payment, $window->paymentPollInterval());
        static::assertNotSame($maxAge, $window->maxPollingAge());
        static::assertNotSame($window->statusPollInterval(), $window->statusPollInterval());
        static::assertNotSame($window->paymentPollInterval(), $window->paymentPollInterval());
        static::assertNotSame($window->maxPollingAge(), $window->maxPollingAge());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNegativeIntervalPositions(): iterable
    {
        yield 'status' => [0];
        yield 'payment' => [1];
        yield 'max age' => [2];
    }

    #[DataProvider('provideNegativeIntervalPositions')]
    public function testNegativeIntervalsAreRefused(int $position): void
    {
        $negative = (new DateTimeImmutable('2026-01-01'))->diff(new DateTimeImmutable('2025-12-31'));
        $intervals = [new DateInterval('PT1M'), new DateInterval('PT2M'), new DateInterval('PT3M')];
        $intervals[$position] = $negative;

        try {
            PollingWindow::of(...$intervals);
            static::fail('A negative interval must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame('polling_window.negative', $exception->violationCode());
            static::assertSame('Invalid polling_window: intervals must not be negative.', $exception->getMessage());
        }
    }

    public function testTheMaximumAgeIsExceededStrictlyAfterIt(): void
    {
        $window = PollingWindow::of(new DateInterval('PT1M'), new DateInterval('PT2M'), new DateInterval('PT1H'));
        $createdAt = SubmissionFixtures::now();

        static::assertFalse($window->isExpired($createdAt, SubmissionFixtures::at('2026-01-15T11:00:00Z')));
        static::assertTrue($window->isExpired($createdAt, SubmissionFixtures::at('2026-01-15T11:00:01Z')));
    }

    public function testAPollIsDueOnceTheIntervalHasElapsed(): void
    {
        $window = PollingWindow::of(new DateInterval('PT1M'), new DateInterval('PT2M'), new DateInterval('PT1H'));
        $polledAt = SubmissionFixtures::now();

        static::assertTrue($window->isStatusPollDue(null, $polledAt));
        static::assertFalse($window->isStatusPollDue($polledAt, SubmissionFixtures::at('2026-01-15T10:00:59Z')));
        static::assertTrue($window->isStatusPollDue($polledAt, SubmissionFixtures::at('2026-01-15T10:01:00Z')));
        static::assertTrue($window->isStatusPollDue($polledAt, SubmissionFixtures::at('2026-01-15T10:01:01Z')));

        static::assertTrue($window->isPaymentPollDue(null, $polledAt));
        static::assertFalse($window->isPaymentPollDue($polledAt, SubmissionFixtures::at('2026-01-15T10:01:59Z')));
        static::assertTrue($window->isPaymentPollDue($polledAt, SubmissionFixtures::at('2026-01-15T10:02:00Z')));
    }

    /**
     * Rewrites a DateInterval in place, as a caller holding a reference could.
     */
    private function reinitialise(DateInterval $interval): void
    {
        \call_user_func([$interval, '__construct'], 'PT9M');
    }
}
