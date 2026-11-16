<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncAllSubmissionStatuses;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncAllSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatuses;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReportItem;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SyncReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

final class SyncSubmissionStatusesHandlerTest extends TestCase
{
    private const LATER = '2026-01-15T10:30:00Z';

    public function testEachDueSubmissionIsReadOnItsPlatformThenAppliedInItsOwnTransaction(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess);
        $harness->declaration(2, SubmissionState::InProcess);
        $harness->identification(3, SubmissionState::Accepted);
        $harness->identification(4, SubmissionState::InProcess, Harness::OTHER_TENANT);
        $harness->clock->moveTo(self::LATER);
        $harness->gateway
            ->willGetIdentification(ReadModels::identification('file-1.zip', 'urssaf-1', 'ACCEPTED'))
            ->willGetDeclaration(ReadModels::declaration('file-2.zip', 'urssaf-2', 'IN_PROCESS', null));

        $report = self::sync($harness, null);

        static::assertEquals([
            new ReportItem('sub-1', SyncSubmissionStatusesHandler::CHANGED),
            new ReportItem('sub-2', SyncSubmissionStatusesHandler::UNCHANGED),
        ], $report->items());
        static::assertSame(['get_identification:urssaf-1', 'begin', 'commit', 'publish:SubmissionAccepted', 'get_declaration:urssaf-2', 'begin', 'commit'], $harness->journal->entries());
        static::assertSame([Harness::TENANT . '|' . Harness::PLATFORM, Harness::TENANT . '|' . Harness::PLATFORM], $harness->gateway->recorded()->targets());
        static::assertSame(SubmissionState::Accepted, self::stored($harness, 'sub-1')->state());
        static::assertSame('ACCEPTED', self::stored($harness, 'sub-1')->snapshot()?->rawStatus());
        static::assertSame(self::LATER, self::stored($harness, 'sub-2')->lastPolledAt()?->format());
        static::assertSame(['urssaf.pn.submission.accepted'], array_map(static fn(OutboxEntry $entry): string => $entry->event->type(), $harness->outbox->pending(10)));
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess);
        $harness->identification(2, SubmissionState::InProcess);
        $harness->gateway->willGetIdentification(ReadModels::identification('file-1.zip', 'urssaf-1', 'IN_PROCESS'));

        $report = self::sync($harness, 1);

        static::assertSame(1, $report->total());
    }

    public function testAnUnknownOrRefusedReadKeepsTheRecordAndTheRunGoesOn(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess);
        $harness->identification(2, SubmissionState::InProcess);
        $harness->identification(3, SubmissionState::InProcess);
        $harness->gateway->willGetIdentification(
            UrssafResourceNotFound::withErrors(Operation::GetIdentification, ApiErrors::none()),
            UrssafRequestRejected::withErrors(Operation::GetIdentification, 400, ApiErrors::none()),
            ReadModels::identification('file-3.zip', 'urssaf-3', 'REJECTED'),
        );

        $report = self::sync($harness, null);

        static::assertEquals([
            new ReportItem('sub-1', SyncSubmissionStatusesHandler::NOT_FOUND),
            new ReportItem('sub-2', SyncSubmissionStatusesHandler::FAILED),
            new ReportItem('sub-3', SyncSubmissionStatusesHandler::CHANGED),
        ], $report->items());
        foreach (['sub-1', 'sub-2'] as $id) {
            $stored = self::stored($harness, $id);
            static::assertSame(SubmissionState::InProcess, $stored->state());
            static::assertNull($stored->snapshot());
            static::assertSame('2026-01-15T10:00:00Z', $stored->updatedAt()->format());
            static::assertSame($harness->clock->now()->format('Y-m-d\TH:i:s\Z'), $stored->lastPolledAt()?->format());
        }
        static::assertSame(SubmissionState::Rejected, self::stored($harness, 'sub-3')->state());
    }

    public function testASubmissionAnswered404IsPolledAfterTheOthersOnTheNextRun(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess);
        $harness->identification(2, SubmissionState::InProcess);
        $harness->gateway->willGetIdentification(
            UrssafResourceNotFound::withErrors(Operation::GetIdentification, ApiErrors::none()),
            ReadModels::identification('file-2.zip', 'urssaf-2', 'IN_PROCESS'),
        );

        $first = self::sync($harness, 1);
        $second = self::sync($harness, 1);

        static::assertEquals([new ReportItem('sub-1', SyncSubmissionStatusesHandler::NOT_FOUND)], $first->items());
        static::assertEquals([new ReportItem('sub-2', SyncSubmissionStatusesHandler::UNCHANGED)], $second->items());
    }

    public function testRefusedCredentialsStopTheRunWithTheReportSoFar(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess);
        $harness->identification(2, SubmissionState::InProcess);
        $harness->identification(3, SubmissionState::InProcess);
        $harness->gateway->willGetIdentification(
            ReadModels::identification('file-1.zip', 'urssaf-1', 'IN_PROCESS'),
            UrssafAuthenticationFailed::withStatus(403),
        );

        $report = self::sync($harness, null);

        static::assertEquals([
            new ReportItem('sub-1', SyncSubmissionStatusesHandler::UNCHANGED),
            new ReportItem('sub-2', SyncSubmissionStatusesHandler::INVALID_CREDENTIALS),
        ], $report->items());
    }

    public function testATemporaryFailureStopsTheRunAndKeepsTheProgress(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess);
        $harness->identification(2, SubmissionState::InProcess);
        $unavailable = UrssafUnavailable::withStatus(Operation::GetIdentification, 503, 60, ApiErrors::none());
        $harness->gateway->willGetIdentification(ReadModels::identification('file-1.zip', 'urssaf-1', 'ACCEPTED'), $unavailable);

        try {
            self::sync($harness, null);
            static::fail('The temporary failure must be thrown.');
        } catch (RetryableUrssafFailure $failure) {
            static::assertSame($unavailable, $failure->getPrevious());
        }

        static::assertSame(SubmissionState::Accepted, self::stored($harness, 'sub-1')->state());
    }

    public function testEveryGivenTenantIsSyncedAndRefusedCredentialsEndOnlyTheirOwnRun(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess);
        $harness->identification(2, SubmissionState::InProcess);
        $harness->identification(3, SubmissionState::InProcess, Harness::OTHER_TENANT);
        $harness->identification(4, SubmissionState::InProcess, Harness::OTHER_TENANT);
        $harness->gateway->willGetIdentification(
            UrssafAuthenticationFailed::withStatus(401),
            ReadModels::identification('file-3.zip', 'urssaf-3', 'IN_PROCESS'),
        );
        $handler = new SyncAllSubmissionStatusesHandler(self::handler($harness));

        $report = $handler(SyncAllSubmissionStatuses::forTenants(1, Harness::tenant(), Harness::tenant(Harness::OTHER_TENANT)));

        static::assertEquals([
            new ReportItem('sub-1', SyncSubmissionStatusesHandler::INVALID_CREDENTIALS),
            new ReportItem('sub-3', SyncSubmissionStatusesHandler::UNCHANGED),
        ], $report->items());
        static::assertSame([Harness::TENANT . '|' . Harness::PLATFORM, Harness::OTHER_TENANT . '|' . Harness::OTHER_PLATFORM], $harness->gateway->recorded()->targets());
        static::assertSame(0, $handler(SyncAllSubmissionStatuses::forTenants(null))->total());
        $tenant = Harness::tenant();
        static::assertSame([$tenant], SyncAllSubmissionStatuses::forTenants(null, ...['first' => $tenant])->tenantIds);
    }

    private static function sync(Harness $harness, ?int $limit): SyncReport
    {
        return self::handler($harness)(SyncSubmissionStatuses::forTenant(Harness::tenant(), $limit));
    }

    private static function handler(Harness $harness): SyncSubmissionStatusesHandler
    {
        return new SyncSubmissionStatusesHandler($harness->submissions, $harness->gateway, $harness->clock, $harness->unitOfWork, $harness->policy);
    }

    private static function stored(Harness $harness, string $id): Submission
    {
        return $harness->submissions->get(Harness::tenant(), SubmissionId::fromString($id));
    }
}
