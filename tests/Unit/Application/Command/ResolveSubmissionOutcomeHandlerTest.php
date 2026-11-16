<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ResolveSubmissionOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ResolveSubmissionOutcomeHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

final class ResolveSubmissionOutcomeHandlerTest extends TestCase
{
    public function testAReceivedUploadMovesToInProcessWithTheGivenReference(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown);

        $view = self::handler($harness)(ResolveSubmissionOutcome::received(
            Harness::tenant(),
            SubmissionId::fromString('sub-1'),
            UrssafReference::fromString('urssaf-operator'),
        ));

        static::assertSame(['in_process', 'urssaf-operator'], [$view->state, $view->urssaf->urssafRef]);
        $stored = $harness->submissions->get(Harness::tenant(), SubmissionId::fromString('sub-1'));
        static::assertSame(SubmissionState::InProcess, $stored->state());
        static::assertSame('urssaf-operator', $stored->urssafReference()?->value());
        static::assertSame(['begin', 'commit', 'publish:SubmissionUploaded'], $harness->journal->entries());
        static::assertSame(['urssaf.pn.submission.uploaded'], self::outboxTypes($harness));
    }

    public function testAReferenceAlreadyHeldByAnotherSubmissionIsRefused(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown);
        $harness->identification(2, SubmissionState::InProcess);
        $held = $harness->submissions->get(Harness::tenant(), SubmissionId::fromString('sub-2'))->urssafReference();
        static::assertNotNull($held);

        try {
            self::handler($harness)(ResolveSubmissionOutcome::received(Harness::tenant(), SubmissionId::fromString('sub-1'), $held));
            static::fail('A reference held by another submission must be refused.');
        } catch (InvariantViolation $refused) {
            static::assertSame('submission.urssaf_reference_already_recorded', $refused->violationCode());
            static::assertSame(['urssaf_ref' => $held->value()], $refused->params());
        }

        static::assertSame(SubmissionState::OutcomeUnknown, $harness->submissions->get(Harness::tenant(), SubmissionId::fromString('sub-1'))->state());
        static::assertSame(['begin', 'rollback'], $harness->journal->entries());
    }

    public function testAnUploadNotReceivedMovesToNotReceived(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::OutcomeUnknown);

        $view = self::handler($harness)(ResolveSubmissionOutcome::notReceived(Harness::tenant(), SubmissionId::fromString('sub-1')));

        static::assertSame('not_received', $view->state);
        static::assertSame(SubmissionState::NotReceived, $harness->submissions->get(Harness::tenant(), SubmissionId::fromString('sub-1'))->state());
        static::assertSame(['urssaf.pn.submission.not_received'], self::outboxTypes($harness));
    }

    public function testOnlyAnUnknownOutcomeCanBeResolved(): void
    {
        $harness = new Harness();
        $harness->identification(1, SubmissionState::InProcess);

        try {
            self::handler($harness)(ResolveSubmissionOutcome::notReceived(Harness::tenant(), SubmissionId::fromString('sub-1')));
            static::fail('A submission in process cannot be resolved.');
        } catch (InvalidStateTransition $refused) {
            static::assertSame('in_process', $refused->from());
        }

        static::assertSame(['begin', 'rollback'], $harness->journal->entries());
    }

    private static function handler(Harness $harness): ResolveSubmissionOutcomeHandler
    {
        return new ResolveSubmissionOutcomeHandler($harness->submissions, $harness->clock, $harness->unitOfWork);
    }

    /**
     * @return list<string>
     */
    private static function outboxTypes(Harness $harness): array
    {
        return array_map(static fn(OutboxEntry $entry): string => $entry->event->type(), $harness->outbox->pending(10));
    }
}
