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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

final class SubmissionStatePredicatesTest extends TestCase
{
    public function testValues(): void
    {
        static::assertSame(
            ['uploading', 'in_process', 'accepted', 'rejected', 'upload_rejected', 'error', 'invalid_credentials', 'outcome_unknown', 'not_received'],
            array_map(static fn(SubmissionState $state): string => $state->value, SubmissionState::cases()),
        );
    }

    /**
     * @return iterable<string, array{SubmissionState, bool, bool, bool}>
     */
    public static function providePredicates(): iterable
    {
        // state => reclaimable, known to URSSAF, occupies the declaration slot
        yield 'uploading' => [SubmissionState::Uploading, false, false, true];
        yield 'in_process' => [SubmissionState::InProcess, false, true, true];
        yield 'accepted' => [SubmissionState::Accepted, false, true, false];
        yield 'rejected' => [SubmissionState::Rejected, false, true, false];
        yield 'upload_rejected' => [SubmissionState::UploadRejected, true, false, false];
        yield 'error' => [SubmissionState::Error, true, false, false];
        yield 'invalid_credentials' => [SubmissionState::InvalidCredentials, true, false, false];
        yield 'outcome_unknown' => [SubmissionState::OutcomeUnknown, false, false, true];
        yield 'not_received' => [SubmissionState::NotReceived, true, false, false];
    }

    #[DataProvider('providePredicates')]
    public function testPredicates(SubmissionState $state, bool $reclaimable, bool $known, bool $occupiesSlot): void
    {
        static::assertSame($reclaimable, $state->isReclaimable());
        static::assertSame($known, $state->isKnownToUrssaf());
        static::assertSame($occupiesSlot, $state->occupiesDeclarationSlot());
        static::assertSame($reclaimable, $state->canTransitionTo(SubmissionState::Uploading));
    }

    public function testAfterPolledStatus(): void
    {
        foreach (SubmissionState::cases() as $state) {
            static::assertSame(SubmissionState::Accepted, $state->afterPolledStatus(FileStatus::Accepted));
            static::assertSame(SubmissionState::Rejected, $state->afterPolledStatus(FileStatus::Rejected));
            static::assertSame($state, $state->afterPolledStatus(FileStatus::InProcess));
            static::assertSame($state, $state->afterPolledStatus(null));
        }
    }
}
