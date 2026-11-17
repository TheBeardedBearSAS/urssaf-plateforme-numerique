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

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Outbox;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\RelayOutboxCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event\Psr14IntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Outbox\InMemoryOutbox;

/**
 * The outbox (written by the UnitOfWork, which autowires it), its sink, relay and purge (the retention is the one of
 * the WorkflowPolicy).
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class OutboxRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $persistence = $config['persistence'];
        $this->registerOutbox($persistence['outbox'], $persistence['driver'], $persistence['entity_manager']);
        $this->registerSink($config['outbox']['sink'], (string) $config['outbox']['service'], $config['messenger']['bus']);
        $this->services->set(RelayOutboxHandler::class)->autowire()->public();
        $this->services->set(PurgeOutboxHandler::class)->autowire()->public();
        $this->services->set(RelayOutboxCommand::class)->autowire()->tag('console.command');
        $this->services->set(PurgeOutboxCommand::class)->autowire()->tag('console.command');
    }

    /**
     * Without "persistence.outbox", the Doctrine outbox of the "doctrine" driver, else the in-memory one (the "custom"
     * driver cannot reach it: it requires "persistence.outbox").
     */
    private function registerOutbox(?string $service, string $driver, string $entityManager): void
    {
        if ($service !== null) {
            $this->services->alias(Outbox::class, $service);

            return;
        }
        if ($driver === BundleConfiguration::DRIVER_DOCTRINE) {
            $this->services->set(Outbox::class, DoctrineOutbox::class)->args([new Reference(ServiceIds::entityManager($entityManager))]);

            return;
        }
        $this->services->set(Outbox::class, InMemoryOutbox::class);
    }

    private function registerSink(string $sink, string $service, string $bus): void
    {
        if ($sink === BundleConfiguration::SINK_MESSENGER) {
            $this->services->set(IntegrationEventSink::class, MessengerIntegrationEventSink::class)->args([new Reference($bus)]);

            return;
        }
        if ($sink === BundleConfiguration::SINK_EVENT_DISPATCHER) {
            $this->services->set(IntegrationEventSink::class, Psr14IntegrationEventSink::class)->args([new Reference('event_dispatcher')]);

            return;
        }
        $this->services->alias(IntegrationEventSink::class, $service);
    }
}
