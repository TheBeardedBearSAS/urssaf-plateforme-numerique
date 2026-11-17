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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RefreshSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RefreshSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

final class RefreshSubmissionHandlerTest extends TestCase
{
    public function testASettledRecordIsReadAgainAndReturnedAsStored(): void
    {
        $harness = new Harness();
        $harness->declaration(1, SubmissionState::Accepted, DeclarationType::Declaration, Harness::OTHER_TENANT);
        $harness->gateway->willGetDeclaration(ReadModels::declaration('file-1.zip', 'urssaf-1', 'REJECTED', 'PROCESSED'));

        $view = self::refresh($harness, Harness::OTHER_TENANT, 'sub-1');

        static::assertSame('rejected', $view->state);
        static::assertSame('REJECTED', $view->urssaf->rawStatus);
        static::assertSame('PROCESSED', $view->urssaf->rawPaymentStatus);
        static::assertSame(Harness::NOW, $view->urssaf->lastPolledAt);
        static::assertSame([Harness::OTHER_TENANT . '|' . Harness::OTHER_PLATFORM], $harness->gateway->recorded()->targets());
        static::assertSame(['get_declaration:urssaf-1', 'begin', 'commit', 'publish:SubmissionRejected', 'publish:PaymentStatusChanged'], $harness->journal->entries());
        static::assertSame(
            SubmissionState::Rejected,
            $harness->submissions->get(Harness::tenant(Harness::OTHER_TENANT), SubmissionId::fromString('sub-1'))->state(),
        );
    }

    public function testARecordNotKnownToUrssafCannotBeRefreshed(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown);

        try {
            self::refresh($harness, Harness::TENANT, 'sub-1');
            static::fail('A record without urssafRef cannot be read.');
        } catch (InvalidStateTransition $refused) {
            static::assertSame(['submission', 'outcome_unknown', 'in_process'], [$refused->aggregate(), $refused->from(), $refused->to()]);
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testARecordOfAnotherTenantIsNotFound(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess, Harness::OTHER_TENANT);

        $this->expectException(RecordNotFound::class);

        self::refresh($harness, Harness::TENANT, 'sub-1');
    }

    public function testATemporaryFailureIsThrownAsRetryableAndTheOthersPropagate(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess);
        $unavailable = UrssafUnavailable::withStatus(Operation::GetIdentification, 429, 5, ApiErrors::none());
        $notFound = UrssafResourceNotFound::withErrors(Operation::GetIdentification, ApiErrors::none());
        $harness->gateway->willGetIdentification($unavailable, $notFound);

        try {
            self::refresh($harness, Harness::TENANT, 'sub-1');
            static::fail('The temporary failure must be thrown.');
        } catch (RetryableUrssafFailure $failure) {
            static::assertSame($unavailable, $failure->getPrevious());
        }
        try {
            self::refresh($harness, Harness::TENANT, 'sub-1');
            static::fail('The unknown reference must propagate.');
        } catch (UrssafResourceNotFound $thrown) {
            static::assertSame($notFound, $thrown);
        }
    }

    private static function refresh(Harness $harness, string $tenant, string $id): SubmissionView
    {
        $handler = new RefreshSubmissionHandler($harness->submissions, $harness->gateway, $harness->clock, $harness->unitOfWork);

        return $handler(RefreshSubmission::of(Harness::tenant($tenant), SubmissionId::fromString($id)));
    }
}
