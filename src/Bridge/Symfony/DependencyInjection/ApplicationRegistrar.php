<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection;

use DateInterval;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissionsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RefreshSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ResolveSubmissionOutcomeHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncAllSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadDeclarationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFileHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\FindSubmissionsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetDeclarationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeedHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchDeclarationsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchIdentificationsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\SubmissionUploader;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Logging\PsrLoggerFailureReporter;

/**
 * Application layer: workflow policy, services and use case handlers (the only public services). The handlers of the
 * overview and of the outbox depend on the configuration ({@see ServiceRegistrar}, {@see OutboxRegistrar}).
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class ApplicationRegistrar
{
    /** Use case handlers exposed to the host application. */
    private const HANDLERS = [
        UploadIdentificationHandler::class,
        UploadDeclarationHandler::class,
        SyncSubmissionStatusesHandler::class,
        SyncAllSubmissionStatusesHandler::class,
        RefreshSubmissionHandler::class,
        ReconcileUnknownSubmissionsHandler::class,
        ResolveSubmissionOutcomeHandler::class,
        GetIdentificationHandler::class,
        GetDeclarationHandler::class,
        SearchIdentificationsHandler::class,
        SearchDeclarationsHandler::class,
        DownloadReportFileHandler::class,
        GetNewsFeedHandler::class,
        GetSubmissionHandler::class,
        FindSubmissionsHandler::class,
    ];

    /** Services shared by the handlers (private, autowired). */
    private const SERVICES = [
        UnitOfWork::class,
        SubmissionUploader::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $workflow = $config['workflow'];
        $this->services->set(WorkflowPolicy::class)->args([
            new Definition(DateInterval::class, [$workflow['status_poll_interval']]),
            new Definition(DateInterval::class, [$workflow['payment_poll_interval']]),
            new Definition(DateInterval::class, [$workflow['max_polling_age']]),
            $workflow['max_upload_attempts'],
            new Definition(DateInterval::class, [$workflow['stale_upload_after']]),
            new Definition(DateInterval::class, [$workflow['manual_review_after']]),
            $workflow['reconciliation_max_pages'],
            $workflow['concurrent_declaration_guard'],
            new Definition(DateInterval::class, [$config['outbox']['retention']]),
            $workflow['default_page_size'],
            $workflow['max_page_size'],
        ]);
        $this->services->set(FailureReporter::class, PsrLoggerFailureReporter::class)
            ->args([new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
            ->tag(ServiceIds::LOGGER_TAG, ['channel' => ServiceIds::LOGGER_CHANNEL]);
        foreach (self::SERVICES as $service) {
            $this->services->set($service)->autowire();
        }
        foreach (self::HANDLERS as $handler) {
            $this->services->set($handler)->autowire()->public();
        }
    }
}
