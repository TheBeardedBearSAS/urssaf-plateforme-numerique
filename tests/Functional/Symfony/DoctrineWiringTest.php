<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Outbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Entity\OutboxMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineSubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemorySubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class DoctrineWiringTest extends TestCase
{
    public function testTheDoctrineDriverWiresTheRepositoryTheStatisticsAndTheTransactionManager(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine'], 'outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']], true);

        static::assertInstanceOf(DoctrineSubmissionRepository::class, $harness->privateService(SubmissionRepository::class, SubmissionRepository::class));
        static::assertInstanceOf(DoctrineWorkflowStatistics::class, $harness->privateService(WorkflowStatistics::class, WorkflowStatistics::class));
        static::assertInstanceOf(DoctrineTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
        static::assertInstanceOf(DoctrineOutbox::class, $harness->privateService(Outbox::class, Outbox::class));
    }

    public function testTheMappingAndTheTypesAreRegisteredInTheDefaultEntityManager(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine']], true);
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        static::assertFalse($entityManager->getMetadataFactory()->isTransient(Submission::class));
        static::assertFalse($entityManager->getMetadataFactory()->isTransient(OutboxMessage::class));
        foreach (array_keys(DoctrineIntegration::types()) as $type) {
            static::assertTrue(Type::hasType($type), $type);
        }
    }

    public function testASubmissionIsStoredInTheDatabase(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine']], true);
        $harness->createDatabaseSchema();

        $report = Scenario::uploadedIdentification($harness);

        $view = $harness->get(GetSubmissionHandler::class)(new GetSubmission(TenantId::fromString(Scenario::TENANT), $report->submissionId));
        static::assertSame('in_process', $view->state);
        static::assertSame(Scenario::IDENTIFICATION_FILE, $view->fileName);
    }

    public function testAnotherEntityManagerCanBeSelected(): void
    {
        $harness = Harness::boot(
            ['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']],
            [
                'dbal' => ['connections' => ['default' => ['driver' => 'pdo_sqlite', 'memory' => true], 'urssaf' => ['driver' => 'pdo_sqlite', 'memory' => true]]],
                'orm' => [
                    'enable_lazy_ghost_objects' => true,
                    'enable_native_lazy_objects' => DoctrineIntegration::nativeLazyObjectsAvailable(),
                    'entity_managers' => ['default' => ['connection' => 'default'], 'urssaf' => ['connection' => 'urssaf']],
                ],
            ],
        );

        $mapped = $harness->privateService('doctrine.orm.urssaf_entity_manager', EntityManagerInterface::class);
        $other = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);
        static::assertFalse($mapped->getMetadataFactory()->isTransient(Submission::class));
        static::assertTrue($other->getMetadataFactory()->isTransient(Submission::class));
    }

    public function testNothingIsMappedWhenAnotherDriverIsSelected(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'in_memory']], true);
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        static::assertTrue($entityManager->getMetadataFactory()->isTransient(Submission::class));
        static::assertInstanceOf(InMemorySubmissionRepository::class, $harness->privateService(SubmissionRepository::class, SubmissionRepository::class));
    }
}
