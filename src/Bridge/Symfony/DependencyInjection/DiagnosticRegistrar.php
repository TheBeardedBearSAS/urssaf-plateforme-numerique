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

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CheckInstallationCommand;
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
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\UrssafPlateformeNumeriqueBundle;

/**
 * The "urssaf:pn:check" command and its checks, in report order (registration order, host checks afterwards unless
 * they have a positive tag priority).
 *
 * The configuration and the probe token provider reach their checks through closures, so that an invalid
 * configuration is reported by the "configuration" check instead of preventing the command from running.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class DiagnosticRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->check(ConfigurationCheck::class, [new ServiceClosureArgument(new Reference(ServiceIds::API_CONFIGURATION))]);
        $this->check(CredentialsCheck::class, [new Reference(CredentialsProvider::class), new Reference(PlatformResolver::class)]);
        [$gatewayCheck, $gatewayArguments] = $config['gateway']['simulated']
            ? [SimulatedGatewayCheck::class, []]
            : [AuthenticationCheck::class, [new Reference(CredentialsProvider::class), new ServiceClosureArgument(new Reference(ServiceIds::PROBE_TOKEN_PROVIDER))]];
        $this->check($gatewayCheck, $gatewayArguments);
        $this->check(TokenStoreCheck::class, [$config['token_store']['service'], $config['token_store']['cache_pool']]);
        $this->check(PersistenceCheck::class, [$config['persistence']['driver']]);
        if ($config['persistence']['driver'] === BundleConfiguration::DRIVER_DOCTRINE) {
            $entityManager = $config['persistence']['entity_manager'];
            $this->check(DatabaseSchemaCheck::class, [new Reference(ServiceIds::entityManager($entityManager)), $entityManager]);
        }
        $this->check(LockCheck::class, [new Reference(OperationLocks::class)]);
        $this->check(MessengerCheck::class, [$config['messenger']['enabled'], $config['messenger']['bus']]);
        $this->check(SchedulerCheck::class, [$config['scheduler']['enabled']]);

        $this->services->set(CheckInstallationCommand::class)
            ->args([new TaggedIteratorArgument(InstallationCheck::TAG), '%' . UrssafPlateformeNumeriqueBundle::PARAMETER_TENANTS . '%'])
            ->tag('console.command');
    }

    /**
     * @param class-string<InstallationCheck> $class
     * @param list<mixed>                     $arguments
     */
    private function check(string $class, array $arguments): void
    {
        $this->services->set($class)->args($arguments)->tag(InstallationCheck::TAG);
    }
}
