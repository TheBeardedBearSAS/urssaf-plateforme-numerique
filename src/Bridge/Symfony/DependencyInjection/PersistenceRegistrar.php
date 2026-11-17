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
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineSubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemorySubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Transaction\ImmediateTransactionManager;

/**
 * Repository, statistics and transaction manager of the selected persistence driver (the outbox is registered with the
 * other outbox services, {@see OutboxRegistrar}).
 */
final readonly class PersistenceRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param array{driver: string, entity_manager: string, repository: ?string, statistics: ?string, transaction_manager: ?string, outbox: ?string} $persistence
     */
    public function register(array $persistence): void
    {
        if ($persistence['driver'] === BundleConfiguration::DRIVER_DOCTRINE) {
            $this->registerDoctrine(new Reference(ServiceIds::entityManager($persistence['entity_manager'])));

            return;
        }
        if ($persistence['driver'] === BundleConfiguration::DRIVER_CUSTOM) {
            $this->registerCustom($persistence);

            return;
        }
        $this->services->set(SubmissionRepository::class, InMemorySubmissionRepository::class);
        $this->services->set(TransactionManager::class, ImmediateTransactionManager::class);
        $this->services->set(WorkflowStatistics::class, InMemoryWorkflowStatistics::class)
            ->args([new Reference(SubmissionRepository::class)]);
    }

    /**
     * Whether a WorkflowStatistics is available: always, except with the "custom" driver without "statistics".
     */
    public static function hasStatistics(string $driver, ?string $statisticsService): bool
    {
        return $driver !== BundleConfiguration::DRIVER_CUSTOM || $statisticsService !== null;
    }

    /**
     * @param array{driver: string, entity_manager: string, repository: ?string, statistics: ?string, transaction_manager: ?string, outbox: ?string} $persistence
     */
    private function registerCustom(array $persistence): void
    {
        $this->services->alias(SubmissionRepository::class, (string) $persistence['repository']);
        $this->registerTransactionManager($persistence['transaction_manager']);
        if ($persistence['statistics'] !== null) {
            $this->services->alias(WorkflowStatistics::class, $persistence['statistics']);
        }
    }

    private function registerTransactionManager(?string $service): void
    {
        if ($service !== null) {
            $this->services->alias(TransactionManager::class, $service);

            return;
        }
        $this->services->set(TransactionManager::class, ImmediateTransactionManager::class);
    }

    private function registerDoctrine(Reference $entityManager): void
    {
        $this->services->set(SubmissionRepository::class, DoctrineSubmissionRepository::class)->args([$entityManager]);
        $this->services->set(TransactionManager::class, DoctrineTransactionManager::class)->args([$entityManager]);
        $this->services->set(WorkflowStatistics::class, DoctrineWorkflowStatistics::class)->args([$entityManager]);
    }
}
