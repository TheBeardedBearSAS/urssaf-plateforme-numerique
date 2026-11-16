<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Standalone;

use LogicException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissionsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RefreshSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ResolveSubmissionOutcomeHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncAllSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadDeclarationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Outbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFileHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\FindSubmissionsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetDeclarationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeedHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchDeclarationsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchIdentificationsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\SubmissionUploader;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;

/**
 * Every use case of the package wired together, for applications without the Symfony bundle; built by
 * {@see UrssafPlateformeNumeriqueBuilder}. Each method returns a new handler (handlers are stateless).
 *
 * One accessor per use case, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class UrssafPlateformeNumerique
{
    public function __construct(
        private PlatformGateway $gateway,
        private PlatformResolver $platforms,
        private SubmissionRepository $submissions,
        private UnitOfWork $unitOfWork,
        private Clock $clock,
        private IdGenerator $ids,
        private WorkflowPolicy $policy,
        private WorkflowStatistics $statistics,
        private Outbox $outbox,
        private ?IntegrationEventSink $sink,
    ) {}

    public function gateway(): PlatformGateway
    {
        return $this->gateway;
    }

    public function uploadIdentification(): UploadIdentificationHandler
    {
        return new UploadIdentificationHandler($this->uploader());
    }

    public function uploadDeclaration(): UploadDeclarationHandler
    {
        return new UploadDeclarationHandler($this->uploader());
    }

    public function syncSubmissionStatuses(): SyncSubmissionStatusesHandler
    {
        return new SyncSubmissionStatusesHandler($this->submissions, $this->gateway, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function syncAllSubmissionStatuses(): SyncAllSubmissionStatusesHandler
    {
        return new SyncAllSubmissionStatusesHandler($this->syncSubmissionStatuses());
    }

    public function refreshSubmission(): RefreshSubmissionHandler
    {
        return new RefreshSubmissionHandler($this->submissions, $this->gateway, $this->clock, $this->unitOfWork);
    }

    public function reconcileUnknownSubmissions(): ReconcileUnknownSubmissionsHandler
    {
        return new ReconcileUnknownSubmissionsHandler($this->submissions, $this->gateway, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function resolveSubmissionOutcome(): ResolveSubmissionOutcomeHandler
    {
        return new ResolveSubmissionOutcomeHandler($this->submissions, $this->clock, $this->unitOfWork);
    }

    public function getIdentification(): GetIdentificationHandler
    {
        return new GetIdentificationHandler($this->gateway, $this->platforms);
    }

    public function getDeclaration(): GetDeclarationHandler
    {
        return new GetDeclarationHandler($this->gateway, $this->platforms);
    }

    public function searchIdentifications(): SearchIdentificationsHandler
    {
        return new SearchIdentificationsHandler($this->gateway, $this->platforms);
    }

    public function searchDeclarations(): SearchDeclarationsHandler
    {
        return new SearchDeclarationsHandler($this->gateway, $this->platforms);
    }

    public function downloadReportFile(): DownloadReportFileHandler
    {
        return new DownloadReportFileHandler($this->gateway, $this->platforms);
    }

    public function getNewsFeed(): GetNewsFeedHandler
    {
        return new GetNewsFeedHandler($this->gateway, $this->platforms);
    }

    public function getSubmission(): GetSubmissionHandler
    {
        return new GetSubmissionHandler($this->submissions);
    }

    public function findSubmissions(): FindSubmissionsHandler
    {
        return new FindSubmissionsHandler($this->submissions, $this->policy);
    }

    public function getWorkflowOverview(): GetWorkflowOverviewHandler
    {
        return new GetWorkflowOverviewHandler($this->statistics);
    }

    /**
     * @throws LogicException when no outbox sink was given
     */
    public function relayOutbox(): RelayOutboxHandler
    {
        return new RelayOutboxHandler(
            $this->outbox,
            $this->sink ?? throw new LogicException('No IntegrationEventSink: give the outbox and its sink (withOutbox()).'),
            $this->clock,
            $this->policy,
        );
    }

    public function purgeOutbox(): PurgeOutboxHandler
    {
        return new PurgeOutboxHandler($this->outbox, $this->clock, $this->policy);
    }

    private function uploader(): SubmissionUploader
    {
        return new SubmissionUploader(
            $this->submissions,
            $this->gateway,
            $this->platforms,
            $this->ids,
            $this->clock,
            $this->unitOfWork,
            $this->policy,
        );
    }
}
