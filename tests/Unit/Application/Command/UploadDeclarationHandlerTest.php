<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\FileFingerprinter;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

/**
 * Declarations, and the guard of ADR 0008: one declaration of each type in process per tenant.
 */
final class UploadDeclarationHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{SubmissionState}>
     */
    public static function occupyingStates(): iterable
    {
        yield 'uploading' => [SubmissionState::Uploading];
        yield 'in process' => [SubmissionState::InProcess];
        yield 'outcome unknown' => [SubmissionState::OutcomeUnknown];
    }

    public function testADeclarationIsSentWithItsType(): void
    {
        $harness = new Harness();
        $harness->gateway->willUploadDeclaration(UrssafReference::fromString('urssaf-new'));

        $report = UploadScenario::declaration($harness, DeclarationType::Regularisation, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::Uploaded, $report->outcome);
        static::assertSame(['upload_declaration:REGULARISATION:' . UploadScenario::FILE], $harness->gateway->recorded()->calls());
        static::assertSame([Harness::TENANT . '|' . Harness::PLATFORM], $harness->gateway->recorded()->targets());
        $identity = UploadScenario::stored($harness, UploadScenario::FIRST_ID)->identity();
        static::assertSame(SubmissionKind::Declaration, $identity->kind());
        static::assertSame(DeclarationType::Regularisation, $identity->declarationType());
        static::assertTrue($identity->fingerprint()->equals(
            FileFingerprinter::fingerprint(UploadScenario::file('content'), SubmissionKind::Declaration, DeclarationType::Regularisation),
        ));
    }

    #[DataProvider('occupyingStates')]
    public function testADeclarationIsRefusedWhileAnotherOneOfTheSameTypeIsInProcess(SubmissionState $state): void
    {
        $harness = new Harness();
        $harness->declaration(1, $state, DeclarationType::Regularisation);

        try {
            UploadScenario::declaration($harness, DeclarationType::Regularisation, UploadScenario::file('content'));
            static::fail('The declaration must be refused.');
        } catch (InvariantViolation $refused) {
            static::assertSame('submission.declaration_in_process', $refused->violationCode());
            static::assertSame(['declaration_type' => 'REGULARISATION'], $refused->params());
            static::assertSame(
                'Another submission of type REGULARISATION is in process for the tenant: URSSAF processes one at a time.',
                $refused->getMessage(),
            );
        }

        static::assertNull($harness->submissions->findByExternalReference(Harness::tenant(), ExternalReference::fromString(UploadScenario::REFERENCE)));
        static::assertSame(['begin', 'rollback'], $harness->journal->entries());
    }

    public function testTheGuardIgnoresTheOtherTypesTheOtherTenantsAndTheSettledDeclarations(): void
    {
        $harness = new Harness();
        $harness->declaration(1, SubmissionState::InProcess, DeclarationType::Declaration);
        $harness->declaration(2, SubmissionState::InProcess, DeclarationType::Regularisation, Harness::OTHER_TENANT);
        $harness->declaration(3, SubmissionState::Accepted, DeclarationType::Regularisation);
        $harness->identification(4, SubmissionState::InProcess);
        $harness->gateway->willUploadDeclaration(UrssafReference::fromString('urssaf-new'));

        $report = UploadScenario::declaration($harness, DeclarationType::Regularisation, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::Uploaded, $report->outcome);
    }

    public function testThePolicyMayDisableTheGuard(): void
    {
        $harness = new Harness((new WorkflowPolicy())->withoutConcurrentDeclarationGuard());
        $harness->declaration(1, SubmissionState::InProcess, DeclarationType::Declaration);
        $harness->gateway->willUploadDeclaration(UrssafReference::fromString('urssaf-new'));

        $report = UploadScenario::declaration($harness, DeclarationType::Declaration, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::Uploaded, $report->outcome);
    }

    public function testTheSameDeclarationInProcessIsReplayedNotRefused(): void
    {
        $harness = new Harness();
        $harness->gateway->willUploadDeclaration(UrssafReference::fromString('urssaf-new'));
        UploadScenario::declaration($harness, DeclarationType::Declaration, UploadScenario::file('content'));

        $replayed = UploadScenario::declaration($harness, DeclarationType::Declaration, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::Replayed, $replayed->outcome);
        static::assertSame('urssaf-new', $replayed->urssafReference?->value());
    }

    public function testAReclaimIsGuardedTooButNotByTheReclaimedRecordItself(): void
    {
        $harness = new Harness();
        $rejected = UrssafRequestRejected::withErrors(Operation::UploadDeclaration, 400, ApiErrors::none());
        $harness->gateway->willUploadDeclaration($rejected, UrssafReference::fromString('urssaf-new'));
        UploadScenario::declaration($harness, DeclarationType::Declaration, UploadScenario::file('content'));

        $reclaimed = UploadScenario::declaration($harness, DeclarationType::Declaration, UploadScenario::file('content'));

        static::assertSame(UploadOutcome::Uploaded, $reclaimed->outcome);

        $harness = new Harness();
        $harness->gateway->willUploadDeclaration($rejected);
        UploadScenario::declaration($harness, DeclarationType::Declaration, UploadScenario::file('content'));
        $harness->declaration(1, SubmissionState::InProcess, DeclarationType::Declaration);

        $this->expectException(InvariantViolation::class);

        UploadScenario::declaration($harness, DeclarationType::Declaration, UploadScenario::file('content'));
    }
}
