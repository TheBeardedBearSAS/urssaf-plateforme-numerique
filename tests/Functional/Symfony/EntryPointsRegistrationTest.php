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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\DownloadReportCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\NewsFeedCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\ReconcileSubmissionsCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\ResolveSubmissionCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\ShowSubmissionCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\SyncSubmissionsCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\UploadDeclarationCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\UploadIdentificationCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\WorkflowStatusCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\AuthenticationCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\ConfigurationCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\DatabaseSchemaCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\InstallationCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\LockCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\MessengerCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\PersistenceCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\SchedulerCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\ReconcileTenantSubmissionsMessageHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\SyncTenantSubmissionsMessageHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;

/**
 * The console commands, the Messenger handlers and the checks of "urssaf:pn:check", as declared by the registrars.
 */
final class EntryPointsRegistrationTest extends TestCase
{
    private const TENANTS = '%urssaf_plateforme_numerique.tenants%';

    public function testTheCommandsWithAnOptionalTenantFallBackOnTheConfiguredTenants(): void
    {
        $container = (new TestKernel())->definitions();

        foreach ([UploadIdentificationCommand::class, UploadDeclarationCommand::class, SyncSubmissionsCommand::class, ReconcileSubmissionsCommand::class, NewsFeedCommand::class] as $command) {
            $definition = $container->getDefinition($command);
            static::assertTrue($definition->isAutowired(), $command);
            static::assertTrue($definition->hasTag('console.command'), $command);
            static::assertSame(['$configuredTenants' => self::TENANTS], $definition->getArguments(), $command);
        }
    }

    public function testTheCommandsWithAMandatoryTenantAreAutowired(): void
    {
        $container = (new TestKernel())->definitions();

        foreach ([ResolveSubmissionCommand::class, ShowSubmissionCommand::class, DownloadReportCommand::class, WorkflowStatusCommand::class] as $command) {
            $definition = $container->getDefinition($command);
            static::assertTrue($definition->isAutowired(), $command);
            static::assertTrue($definition->hasTag('console.command'), $command);
            static::assertSame([], $definition->getArguments(), $command);
        }
    }

    public function testTheMessageHandlersAreOnlyRegisteredWithMessenger(): void
    {
        $handlers = [SyncTenantSubmissionsMessageHandler::class, ReconcileTenantSubmissionsMessageHandler::class];
        $without = (new TestKernel())->definitions();
        $with = (new TestKernel(['messenger' => ['enabled' => true]], false, true))->definitions();

        foreach ($handlers as $handler) {
            static::assertFalse($without->has($handler), $handler);
            static::assertTrue($with->getDefinition($handler)->isAutowired(), $handler);
            static::assertTrue($with->getDefinition($handler)->hasTag('messenger.message_handler'), $handler);
        }
    }

    public function testTheCheckCommandRunsTheTaggedChecksForTheConfiguredTenants(): void
    {
        $definition = (new TestKernel())->definitions()->getDefinition(CheckInstallationCommand::class);

        static::assertEquals([new TaggedIteratorArgument(InstallationCheck::TAG), self::TENANTS], $definition->getArguments());
        static::assertTrue($definition->hasTag('console.command'));
    }

    public function testTheChecksInReportOrder(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertSame(
            [ConfigurationCheck::class, CredentialsCheck::class, AuthenticationCheck::class, TokenStoreCheck::class, PersistenceCheck::class, LockCheck::class, MessengerCheck::class, SchedulerCheck::class],
            self::checks($container),
        );
        static::assertEquals([new ServiceClosureArgument(new Reference(ServiceIds::API_CONFIGURATION))], $container->getDefinition(ConfigurationCheck::class)->getArguments());
        static::assertEquals([new Reference(CredentialsProvider::class), new Reference(PlatformResolver::class)], $container->getDefinition(CredentialsCheck::class)->getArguments());
        static::assertEquals(
            [new Reference(CredentialsProvider::class), new ServiceClosureArgument(new Reference(ServiceIds::PROBE_TOKEN_PROVIDER))],
            $container->getDefinition(AuthenticationCheck::class)->getArguments(),
        );
        static::assertSame([null, 'cache.app'], $container->getDefinition(TokenStoreCheck::class)->getArguments());
        static::assertSame(['in_memory'], $container->getDefinition(PersistenceCheck::class)->getArguments());
        static::assertEquals([new Reference(OperationLocks::class)], $container->getDefinition(LockCheck::class)->getArguments());
        static::assertSame([false, 'messenger.default_bus'], $container->getDefinition(MessengerCheck::class)->getArguments());
        static::assertSame([false], $container->getDefinition(SchedulerCheck::class)->getArguments());
    }

    public function testTheChecksFollowTheConfiguration(): void
    {
        $container = (new TestKernel([
            'gateway' => ['simulated' => true],
            'token_store' => ['service' => 'test.token_store'],
            'persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf'],
            'messenger' => ['enabled' => true, 'bus' => 'my.bus'],
            'scheduler' => ['enabled' => true],
        ], true, true))->definitions();

        static::assertSame(
            [ConfigurationCheck::class, CredentialsCheck::class, SimulatedGatewayCheck::class, TokenStoreCheck::class, PersistenceCheck::class, DatabaseSchemaCheck::class, LockCheck::class, MessengerCheck::class, SchedulerCheck::class],
            self::checks($container),
        );
        static::assertSame([], $container->getDefinition(SimulatedGatewayCheck::class)->getArguments());
        static::assertSame(['test.token_store', 'cache.app'], $container->getDefinition(TokenStoreCheck::class)->getArguments());
        static::assertSame(['doctrine'], $container->getDefinition(PersistenceCheck::class)->getArguments());
        static::assertEquals([new Reference('doctrine.orm.urssaf_entity_manager'), 'urssaf'], $container->getDefinition(DatabaseSchemaCheck::class)->getArguments());
        static::assertSame([true, 'my.bus'], $container->getDefinition(MessengerCheck::class)->getArguments());
        static::assertSame([true], $container->getDefinition(SchedulerCheck::class)->getArguments());
    }

    public function testTheChecksOfTheHostAreTaggedAutomatically(): void
    {
        $instanceof = (new TestKernel())->definitions()->getAutoconfiguredInstanceof();

        static::assertArrayHasKey(InstallationCheck::class, $instanceof);
        static::assertSame([InstallationCheck::TAG => [[]]], $instanceof[InstallationCheck::class]->getTags());
    }

    /**
     * @return list<string> the ids of the checks, in registration order
     */
    private static function checks(ContainerBuilder $container): array
    {
        return array_keys($container->findTaggedServiceIds(InstallationCheck::TAG));
    }
}
