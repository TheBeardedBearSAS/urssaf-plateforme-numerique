<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\ConstraintViolationList;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\WorkflowStatusCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\UrssafPlateformeNumeriqueBundle;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Validation\ViolationListConverter;

/**
 * Declares the services of the bundle from its processed configuration. Services are private (the default of the
 * container configurator), except the use case handlers (the entry points of the host application), the simulated
 * gateway (tests) and the violation list converter (registered when symfony/validator is installed).
 *
 * @phpstan-type Config array{
 *     environment: string,
 *     base_uri: ?string,
 *     accept_language: string,
 *     api_timezone: string,
 *     request_id_header: ?string,
 *     token_safety_margin: int,
 *     http_client: ?string,
 *     http_timeout: float,
 *     credentials_provider: ?string,
 *     platform_resolver: ?string,
 *     credentials: array<string, array{client_id: string, client_secret: string, platform_id: string}>,
 *     tenants: list<string>,
 *     token_store: array{cache_pool: string, service: ?string},
 *     gateway: array{simulated: bool},
 *     rate_limiter: array{enabled: bool, max_calls: int, window_seconds: int},
 *     lock: array{enabled: bool|string, factory: string, ttl: float},
 *     outbox: array{enabled: bool, sink: string, service: ?string, retention: string},
 *     scheduler: array{enabled: bool, sync_frequency: ?string, reconcile_frequency: ?string},
 *     workflow: array{status_poll_interval: string, payment_poll_interval: string, max_polling_age: string, max_upload_attempts: int, stale_upload_after: string, manual_review_after: string, reconciliation_max_pages: int, concurrent_declaration_guard: bool, default_page_size: int, max_page_size: int},
 *     persistence: array{driver: string, entity_manager: string, repository: ?string, statistics: ?string, transaction_manager: ?string, outbox: ?string},
 *     messenger: array{enabled: bool, bus: string}
 * }
 */
final readonly class ServiceRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        (new InfrastructureRegistrar($this->services))->register($config);
        (new GatewayRegistrar($this->services))->register($config);
        (new PersistenceRegistrar($this->services))->register($config['persistence']);
        (new LockRegistrar($this->services))->register($config['lock']);
        (new ApplicationRegistrar($this->services))->register($config);
        $entryPoints = new EntryPointRegistrar($this->services);
        $entryPoints->registerConsole();
        (new DiagnosticRegistrar($this->services))->register($config);
        if (PersistenceRegistrar::hasStatistics($config['persistence']['driver'], $config['persistence']['statistics'])) {
            $this->services->set(GetWorkflowOverviewHandler::class)->autowire()->public();
            $this->services->set(WorkflowStatusCommand::class)->autowire()->tag('console.command');
        }
        // The schedule dispatches the messages: their handlers are needed even when "messenger.enabled" is off.
        if ($config['messenger']['enabled'] || $config['scheduler']['enabled']) {
            $entryPoints->registerMessenger();
        }
        if ($config['outbox']['enabled']) {
            (new OutboxRegistrar($this->services))->register($config);
        }
        if ($config['scheduler']['enabled']) {
            $this->services->set(UrssafScheduleProvider::class)
                ->args([
                    '%' . UrssafPlateformeNumeriqueBundle::PARAMETER_TENANTS . '%',
                    $config['scheduler']['sync_frequency'],
                    $config['scheduler']['reconcile_frequency'],
                ])
                ->tag('scheduler.schedule_provider', ['name' => ServiceIds::SCHEDULE]);
        }
        if (class_exists(ConstraintViolationList::class)) {
            $this->services->set(ViolationListConverter::class)
                ->args([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->public();
        }
    }
}
