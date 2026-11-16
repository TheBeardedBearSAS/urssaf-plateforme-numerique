<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemorySubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Platform\InMemoryPlatformResolver;

/**
 * Wires the fakes together and builds submissions in a given state. Time: NOW (2026-01-15T10:00:00Z). Two tenants,
 * each with its own platform.
 *
 * - identification(n, state) / declaration(n, state, type): submission "sub-<n>" (external reference "ref-<n>", file
 *   "file-<n>.zip", urssafRef "urssaf-<n>" once known) driven to the state through the aggregate API, stored with its
 *   events released.
 */
final readonly class Harness
{
    public const TENANT = 'tenant-1';
    public const OTHER_TENANT = 'tenant-2';
    public const PLATFORM = 'platform-1';
    public const OTHER_PLATFORM = 'platform-2';
    public const NOW = '2026-01-15T10:00:00Z';

    public Journal $journal;
    public FixedClock $clock;
    public SequentialIdGenerator $ids;
    public RecordingEventPublisher $events;
    public RecordingTransactionManager $transactions;
    public InMemoryOutbox $outbox;
    public UnitOfWork $unitOfWork;
    public InMemorySubmissionRepository $submissions;
    public InMemoryPlatformResolver $platforms;
    public FakePlatformGateway $gateway;

    public function __construct(public WorkflowPolicy $policy = new WorkflowPolicy())
    {
        $this->journal = new Journal();
        $this->clock = new FixedClock(self::NOW);
        $this->ids = new SequentialIdGenerator();
        $this->events = new RecordingEventPublisher($this->journal);
        $this->transactions = new RecordingTransactionManager($this->journal);
        $this->outbox = new InMemoryOutbox();
        $this->unitOfWork = new UnitOfWork($this->transactions, $this->events, $this->ids, new IgnoredFailures(), $this->outbox);
        $this->submissions = new InMemorySubmissionRepository();
        $this->platforms = new InMemoryPlatformResolver([
            self::TENANT => PlatformId::fromString(self::PLATFORM),
            self::OTHER_TENANT => PlatformId::fromString(self::OTHER_PLATFORM),
        ]);
        $this->gateway = new FakePlatformGateway($this->journal);
    }

    public static function tenant(string $tenant = self::TENANT): TenantId
    {
        return TenantId::fromString($tenant);
    }

    public static function at(string $iso8601): Instant
    {
        return Instant::fromIso8601($iso8601);
    }

    public static function platform(string $platform = self::PLATFORM): PlatformId
    {
        return PlatformId::fromString($platform);
    }

    public function identification(
        int $number,
        SubmissionState $state,
        string $tenant = self::TENANT,
        string $at = self::NOW,
    ): Submission {
        return $this->store(SubmissionSamples::identity($number, SubmissionKind::Identification, null, $tenant), $state, $number, $at);
    }

    public function declaration(
        int $number,
        SubmissionState $state,
        DeclarationType $type = DeclarationType::Declaration,
        string $tenant = self::TENANT,
        string $at = self::NOW,
    ): Submission {
        return $this->store(SubmissionSamples::identity($number, SubmissionKind::Declaration, $type, $tenant), $state, $number, $at);
    }

    private function store(SubmissionIdentity $identity, SubmissionState $state, int $number, string $at): Submission
    {
        $now = self::at($at);
        $submission = Submission::start($identity, $now);
        if ($state->isKnownToUrssaf()) {
            $submission->markUploaded(SubmissionSamples::urssafReference($number), $now);
        }
        if ($state === SubmissionState::Accepted || $state === SubmissionState::Rejected) {
            $submission->applySnapshot(SubmissionSamples::snapshot(strtoupper($state->value), null), $now);
        }
        if ($state === SubmissionState::OutcomeUnknown || $state === SubmissionState::NotReceived) {
            $submission->markOutcomeUnknown($now);
        }
        if ($state === SubmissionState::NotReceived) {
            $submission->markNotReceived($now);
        }
        if ($state === SubmissionState::UploadRejected) {
            $submission->markUploadRejected([SubmissionError::of('ERR_X', 'x')], $now);
        }
        if ($state === SubmissionState::Error) {
            $submission->markFailed($now);
        }
        if ($state === SubmissionState::InvalidCredentials) {
            $submission->markInvalidCredentials($now);
        }
        $submission->releaseEvents();
        $this->submissions->add($submission);

        return $this->submissions->get($identity->tenantId(), $identity->id());
    }
}
