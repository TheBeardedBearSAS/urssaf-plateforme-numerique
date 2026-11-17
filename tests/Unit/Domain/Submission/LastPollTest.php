<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\LastPoll;

final class LastPollTest extends TestCase
{
    public function testNeverPolled(): void
    {
        $never = LastPoll::never();

        static::assertNull($never->polledAt());
        static::assertNull($never->rawPaymentStatus());
        static::assertFalse($never->isPaymentSettled());
    }

    public function testAnAnswerKeepsTheRawCodesOfTheSnapshot(): void
    {
        $now = SubmissionFixtures::at('2026-03-01T10:00:00Z');

        $poll = LastPoll::answered(SubmissionFixtures::snapshot('ACCEPTED', 'SOMETHING_NEW'), $now);

        static::assertSame($now, $poll->polledAt());
        static::assertSame('SOMETHING_NEW', $poll->rawPaymentStatus());
    }

    public function testAPollWithoutAnswerMovesTheInstantAndKeepsTheCodesOfTheLastAnswer(): void
    {
        $answered = LastPoll::answered(SubmissionFixtures::snapshot('ACCEPTED', 'PROCESSED'), SubmissionFixtures::at('2026-03-01T10:00:00Z'));
        $later = SubmissionFixtures::at('2026-03-01T11:00:00Z');

        $unanswered = $answered->unansweredAt($later);

        static::assertSame($later, $unanswered->polledAt());
        static::assertSame('PROCESSED', $unanswered->rawPaymentStatus());
        static::assertTrue($unanswered->isPaymentSettled());
        static::assertSame('2026-03-01T10:00:00Z', $answered->polledAt()?->format());
        static::assertNull(LastPoll::never()->unansweredAt($later)->rawPaymentStatus());
    }

    /**
     * @return iterable<string, array{string|null, string|null, bool}>
     */
    public static function settlements(): iterable
    {
        yield 'accepted and processed' => ['ACCEPTED', 'PROCESSED', true];
        yield 'accepted, payment in progress' => ['ACCEPTED', 'PROCESSING', false];
        yield 'accepted, no payment status' => ['ACCEPTED', null, false];
        yield 'rejected and processed' => ['REJECTED', 'PROCESSED', false];
        yield 'unknown status' => ['SOMETHING_NEW', 'PROCESSED', false];
        yield 'no status' => [null, 'PROCESSED', false];
    }

    #[DataProvider('settlements')]
    public function testThePaymentIsSettledOnlyWhenAcceptedAndProcessed(?string $status, ?string $paymentStatus, bool $settled): void
    {
        $poll = LastPoll::answered(SubmissionFixtures::snapshot($status, $paymentStatus), SubmissionFixtures::now());

        static::assertSame($settled, $poll->isPaymentSettled());
        static::assertSame($settled, SubmissionFixtures::snapshot($status, $paymentStatus)->isPaymentSettled());
    }
}
