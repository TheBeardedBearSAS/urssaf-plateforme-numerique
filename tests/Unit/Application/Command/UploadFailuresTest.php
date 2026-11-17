<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;
use Throwable;

/**
 * Failures of the upload call that are not a described URSSAF answer: where the record is left, and what is rethrown.
 */
final class UploadFailuresTest extends TestCase
{
    public function testATemporaryFailureThatMayHaveReachedUrssafIsReportedAsUnknownNotThrown(): void
    {
        $harness = new Harness();
        $harness->gateway->willUploadIdentification(UrssafUnavailable::dueToTransportError(Operation::UploadIdentification, 'TimeoutException'));

        $report = UploadScenario::identification($harness, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::OutcomeUnknown, $report->outcome);
        static::assertSame(SubmissionState::OutcomeUnknown, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->state());
        static::assertSame(['SubmissionOutcomeUnknown'], $harness->events->names());
    }

    public static function undescribedAnswers(): iterable
    {
        yield 'unexpected answer' => [UnexpectedUrssafResponse::unexpectedStatus(Operation::UploadIdentification, 302)];
        yield 'credentials not found' => [RecordNotFound::withIdentifier('credentials', Harness::TENANT)];
    }

    #[DataProvider('undescribedAnswers')]
    public function testAnAnswerTheContractDoesNotDescribeMovesTheRecordToErrorThenIsRethrown(Throwable $answer): void
    {
        $harness = new Harness();
        $harness->gateway->willUploadIdentification($answer);

        try {
            UploadScenario::identification($harness, UploadScenario::file('content'));
            static::fail('The failure of the call must be rethrown.');
        } catch (Throwable $failure) {
            static::assertSame($answer, $failure);
        }

        static::assertSame(SubmissionState::Error, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->state());
        static::assertSame(
            ['begin', 'commit', 'upload_identification:' . UploadScenario::FILE, 'begin', 'commit', 'publish:SubmissionUploadFailed'],
            $harness->journal->entries(),
        );
    }

    public function testAnyOtherFailureOfTheCallMovesTheRecordToUnknownThenIsRethrown(): void
    {
        $harness = new Harness();

        try {
            UploadScenario::identification($harness, UploadScenario::file('content'));
            static::fail('The failure of the call must be rethrown.');
        } catch (LogicException $failure) {
            static::assertSame('Unexpected uploadIdentification() call.', $failure->getMessage());
        }

        static::assertSame(SubmissionState::OutcomeUnknown, UploadScenario::stored($harness, UploadScenario::FIRST_ID)->state());
        static::assertSame(
            ['begin', 'commit', 'upload_identification:' . UploadScenario::FILE, 'begin', 'commit', 'publish:SubmissionOutcomeUnknown'],
            $harness->journal->entries(),
        );
    }
}
