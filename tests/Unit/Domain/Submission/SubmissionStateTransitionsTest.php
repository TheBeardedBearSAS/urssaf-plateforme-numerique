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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

final class SubmissionStateTransitionsTest extends TestCase
{
    private const ALLOWED = [
        'uploading' => ['in_process', 'upload_rejected', 'error', 'invalid_credentials', 'outcome_unknown'],
        'in_process' => ['accepted', 'rejected'],
        'accepted' => ['rejected'],
        'rejected' => ['accepted'],
        'upload_rejected' => ['uploading'],
        'error' => ['uploading'],
        'invalid_credentials' => ['uploading'],
        'outcome_unknown' => ['in_process', 'not_received'],
        'not_received' => ['uploading'],
    ];

    /**
     * @return iterable<string, array{SubmissionState, SubmissionState, bool}>
     */
    public static function provideMatrix(): iterable
    {
        foreach (SubmissionState::cases() as $from) {
            foreach (SubmissionState::cases() as $to) {
                yield $from->value . ' -> ' . $to->value => [$from, $to, \in_array($to->value, self::ALLOWED[$from->value], true)];
            }
        }
    }

    #[DataProvider('provideMatrix')]
    public function testCanTransitionTo(SubmissionState $from, SubmissionState $to, bool $allowed): void
    {
        static::assertSame($allowed, $from->canTransitionTo($to));
    }

    /**
     * @return iterable<string, array{SubmissionState, SubmissionState}>
     */
    public static function provideRefusedTransitions(): iterable
    {
        foreach (self::provideMatrix() as $name => [$from, $to, $allowed]) {
            if ($allowed) {
                continue;
            }
            yield $name => [$from, $to];
        }
    }

    #[DataProvider('provideRefusedTransitions')]
    public function testRefusedTransitionThrows(SubmissionState $from, SubmissionState $to): void
    {
        $this->expectExceptionObject(InvalidStateTransition::between('submission', $from->value, $to->value));

        $from->transitionTo($to);
    }

    public function testRefusedTransitionCode(): void
    {
        try {
            SubmissionState::Accepted->transitionTo(SubmissionState::Uploading);
            static::fail('The transition must be refused.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('submission.invalid_state_transition', $exception->violationCode());
            static::assertSame('accepted', $exception->from());
            static::assertSame('uploading', $exception->to());
        }
    }

    public function testAllowedTransitionReturnsTheTarget(): void
    {
        foreach (self::ALLOWED as $from => $targets) {
            foreach ($targets as $to) {
                static::assertSame(SubmissionState::from($to), SubmissionState::from($from)->transitionTo(SubmissionState::from($to)));
            }
        }
    }

    public function testAllowedTransitions(): void
    {
        foreach (self::ALLOWED as $from => $targets) {
            static::assertSame(
                array_map(SubmissionState::from(...), $targets),
                SubmissionState::from($from)->allowedTransitions(),
            );
        }
    }
}
