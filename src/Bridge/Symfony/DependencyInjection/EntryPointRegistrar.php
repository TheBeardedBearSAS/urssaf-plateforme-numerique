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
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\DownloadReportCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\NewsFeedCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\ReconcileSubmissionsCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\ResolveSubmissionCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\ShowSubmissionCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\SyncSubmissionsCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\UploadDeclarationCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\UploadIdentificationCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\ReconcileTenantSubmissionsMessageHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\SyncTenantSubmissionsMessageHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\UrssafPlateformeNumeriqueBundle;

/**
 * Driving adapters: console commands and Messenger handlers (no upload message and no auto-submit listener, ADR 0012).
 * The status, outbox and check commands are registered with the services they need ({@see ServiceRegistrar},
 * {@see OutboxRegistrar}, {@see DiagnosticRegistrar}).
 */
final readonly class EntryPointRegistrar
{
    /** Commands that require "--tenant". */
    private const COMMANDS = [
        ResolveSubmissionCommand::class,
        ShowSubmissionCommand::class,
        DownloadReportCommand::class,
    ];

    /** Commands whose "--tenant" is optional: they fall back on the configured tenants ("$configuredTenants"). */
    private const TENANT_COMMANDS = [
        UploadIdentificationCommand::class,
        UploadDeclarationCommand::class,
        SyncSubmissionsCommand::class,
        ReconcileSubmissionsCommand::class,
        NewsFeedCommand::class,
    ];

    private const MESSAGE_HANDLERS = [
        SyncTenantSubmissionsMessageHandler::class,
        ReconcileTenantSubmissionsMessageHandler::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    public function registerConsole(): void
    {
        foreach (self::COMMANDS as $command) {
            $this->services->set($command)->autowire()->tag('console.command');
        }
        foreach (self::TENANT_COMMANDS as $command) {
            $this->services->set($command)
                ->autowire()
                ->arg('$configuredTenants', '%' . UrssafPlateformeNumeriqueBundle::PARAMETER_TENANTS . '%')
                ->tag('console.command');
        }
    }

    public function registerMessenger(): void
    {
        foreach (self::MESSAGE_HANDLERS as $handler) {
            $this->services->set($handler)->autowire()->tag('messenger.message_handler');
        }
    }
}
