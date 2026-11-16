<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissions;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissionsHandler as Handler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReportItem;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\RacingSubmissionRepository;

/**
 * ADR 0003: matching the uploads of unknown outcome with the URSSAF searches.
 */
final class ReconcileUnknownSubmissionsHandlerTest extends TestCase
{
    public function testTheOnlyResultWithTheUploadedNameAndAnUnknownReferenceIsAdopted(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown);
        $harness->identification(2, SubmissionState::InProcess);
        $harness->gateway->willSearchIdentifications(ReadModels::page([
            ReadModels::identification('other.zip', 'urssaf-other', 'IN_PROCESS'),
            ReadModels::identification('file-1.zip', null, 'IN_PROCESS'),
            ReadModels::identification('file-1.zip', 'urssaf-2', 'IN_PROCESS'),
            ReadModels::identification('file-1.zip', 'urssaf-found', 'ACCEPTED'),
        ]));

        $report = ReconcileScenario::reconcile($harness, null);

        static::assertEquals([new ReportItem('sub-1', Handler::ADOPTED)], $report->items());
        $stored = ReconcileScenario::stored($harness, 'sub-1');
        static::assertSame(SubmissionState::InProcess, $stored->state());
        static::assertSame('urssaf-found', $stored->urssafReference()?->value());
        static::assertSame(['search_identifications', 'begin', 'commit', 'publish:SubmissionUploaded'], $harness->journal->entries());
        static::assertSame(['urssaf.pn.submission.uploaded'], array_map(static fn(OutboxEntry $entry): string => $entry->event->type(), $harness->outbox->pending(10)));
        $search = $harness->gateway->recorded()->searches()[0];
        $page = $search->pageRequest();
        static::assertSame([1, 500], [$page->page(), $page->size()]);
        static::assertSame(['lastUpdateDate:DESC'], array_map(static fn(SortOrder $sort): string => $sort->toWire(), $page->sorts()));
        static::assertSame([null, null, null, null], [$search->search(), $search->startDate(), $search->endDate(), $search->type()]);
    }

    public function testDeclarationsAreSearchedByTypeOnTheirPlatform(): void
    {
        $harness = new Harness();
        $harness->declaration(1, SubmissionState::OutcomeUnknown, DeclarationType::Regularisation, Harness::OTHER_TENANT);
        $harness->gateway->willSearchDeclarations(ReadModels::page([
            ReadModels::declaration('file-1.zip', 'urssaf-found', 'IN_PROCESS', null),
            ReadModels::declaration('file-1.zip', 'urssaf-found', 'IN_PROCESS', null),
        ]));

        $report = ReconcileScenario::handler($harness)(ReconcileUnknownSubmissions::forTenant(Harness::tenant(Harness::OTHER_TENANT), null));

        static::assertEquals([new ReportItem('sub-1', Handler::ADOPTED)], $report->items());
        static::assertSame(DeclarationType::Regularisation, $harness->gateway->recorded()->searches()[0]->type());
        static::assertSame([Harness::OTHER_TENANT . '|' . Harness::OTHER_PLATFORM], $harness->gateway->recorded()->targets());
    }

    public function testNoneOrSeveralCandidatesLeaveTheSubmissionUnknown(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown);
        $harness->identification(2, SubmissionState::OutcomeUnknown);
        $harness->gateway->willSearchIdentifications(
            ReadModels::page([ReadModels::identification(null, 'urssaf-x', 'IN_PROCESS')]),
            ReadModels::page([
                ReadModels::identification('file-2.zip', 'urssaf-a', 'IN_PROCESS'),
                ReadModels::identification('file-2.zip', 'urssaf-b', 'IN_PROCESS'),
            ]),
        );

        $report = ReconcileScenario::reconcile($harness, null);

        static::assertEquals([new ReportItem('sub-1', Handler::UNMATCHED), new ReportItem('sub-2', Handler::AMBIGUOUS)], $report->items());
        static::assertSame(SubmissionState::OutcomeUnknown, ReconcileScenario::stored($harness, 'sub-2')->state());
        static::assertSame(['search_identifications', 'search_identifications'], $harness->journal->entries());
    }

    public function testAnUnmatchedSubmissionUnknownForTooLongNeedsAManualReview(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown, Harness::TENANT, '2026-01-14T10:00:00Z');
        $harness->identification(2, SubmissionState::OutcomeUnknown, Harness::TENANT, '2026-01-14T10:00:01Z');
        $harness->identification(3, SubmissionState::OutcomeUnknown, Harness::TENANT, '2026-01-14T10:00:00Z');
        $harness->gateway->willSearchIdentifications(
            ReadModels::noIdentifications(),
            ReadModels::page([ReadModels::identification('file-3.zip', 'urssaf-found', 'IN_PROCESS')]),
            ReadModels::noIdentifications(),
        );

        $report = ReconcileScenario::reconcile($harness, null);

        static::assertEquals([
            new ReportItem('sub-1', Handler::MANUAL_REVIEW),
            new ReportItem('sub-3', Handler::ADOPTED),
            new ReportItem('sub-2', Handler::UNMATCHED),
        ], $report->items());
    }

    public function testAnUploadInFlightIsLeftAloneUntilStaleThenMovedToUnknownBeforeTheSearch(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::Uploading, Harness::TENANT, '2026-01-15T09:00:00Z');
        $harness->identification(2, SubmissionState::Uploading, Harness::TENANT, '2026-01-15T09:00:01Z');
        $harness->gateway->willSearchIdentifications(ReadModels::noIdentifications());

        $report = ReconcileScenario::reconcile($harness, null);

        static::assertEquals([new ReportItem('sub-1', Handler::UNMATCHED), new ReportItem('sub-2', Handler::IN_FLIGHT)], $report->items());
        static::assertSame(SubmissionState::OutcomeUnknown, ReconcileScenario::stored($harness, 'sub-1')->state());
        static::assertSame(Harness::NOW, ReconcileScenario::stored($harness, 'sub-1')->updatedAt()->format());
        static::assertSame(SubmissionState::Uploading, ReconcileScenario::stored($harness, 'sub-2')->state());
        static::assertSame(['begin', 'commit', 'publish:SubmissionOutcomeUnknown', 'search_identifications'], $harness->journal->entries());
    }

    public function testAStaleUploadCommittedMeanwhileIsLeftUntouchedAndNotSearched(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::Uploading, Harness::TENANT, '2026-01-15T08:00:00Z');
        $racing = (new RacingSubmissionRepository($harness->submissions))->loseNextClaims(1);

        $report = ReconcileScenario::handlerOver($harness, $racing)(ReconcileUnknownSubmissions::forTenant(Harness::tenant(), null));

        static::assertEquals([new ReportItem('sub-1', Handler::SUPERSEDED)], $report->items());
        static::assertSame(SubmissionState::Uploading, ReconcileScenario::stored($harness, 'sub-1')->state());
        static::assertSame('2026-01-15T08:00:00Z', ReconcileScenario::stored($harness, 'sub-1')->updatedAt()->format());
        static::assertSame([], $harness->gateway->recorded()->searches());
        static::assertSame([], $harness->events->events());
        static::assertSame([], $harness->outbox->pending(10));
    }

    public function testThePagesAreSearchedUpToThePolicyMaximumWhileFull(): void
    {
        $twoPages = new Harness(ReconcileScenario::policy(2));
        $twoPages->identification(1, SubmissionState::OutcomeUnknown);
        $twoPages->gateway->willSearchIdentifications(
            ReadModels::fullOfOthers(500),
            ReadModels::page([ReadModels::identification('file-1.zip', 'urssaf-found', 'IN_PROCESS')]),
        );

        static::assertEquals([new ReportItem('sub-1', Handler::ADOPTED)], ReconcileScenario::reconcile($twoPages, null)->items());
        static::assertSame(2, $twoPages->gateway->recorded()->searches()[1]->pageRequest()->page());

        $partialPage = new Harness(ReconcileScenario::policy(2));
        $partialPage->identification(1, SubmissionState::OutcomeUnknown);
        $partialPage->gateway->willSearchIdentifications(ReadModels::fullOfOthers(499));

        static::assertEquals([new ReportItem('sub-1', Handler::UNMATCHED)], ReconcileScenario::reconcile($partialPage, null)->items());

        $onePage = new Harness();
        $onePage->identification(1, SubmissionState::OutcomeUnknown);
        $onePage->gateway->willSearchIdentifications(ReadModels::fullOfOthers(500));

        static::assertEquals([new ReportItem('sub-1', Handler::UNMATCHED)], ReconcileScenario::reconcile($onePage, null)->items());
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown);
        $harness->identification(2, SubmissionState::OutcomeUnknown);
        $harness->gateway->willSearchIdentifications(ReadModels::noIdentifications());

        static::assertSame(1, ReconcileScenario::reconcile($harness, 1)->total());
    }
}
