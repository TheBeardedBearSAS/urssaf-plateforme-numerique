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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;

/**
 * Configuration tree of the "urssaf_plateforme_numerique" bundle configuration (ADR 0012). Every default of the
 * workflow is the one of {@see WorkflowPolicy}.
 */
final class BundleConfiguration
{
    public const DRIVER_IN_MEMORY = 'in_memory';
    public const DRIVER_DOCTRINE = 'doctrine';
    public const DRIVER_CUSTOM = 'custom';

    public const SINK_MESSENGER = 'messenger';
    public const SINK_EVENT_DISPATCHER = 'event_dispatcher';
    public const SINK_CUSTOM = 'custom';

    /** URSSAF publishes no quota (ADR 0002): the defaults of the optional rate limiter are a choice of this package. */
    public const DEFAULT_RATE_LIMIT_MAX_CALLS = 60;
    public const DEFAULT_RATE_LIMIT_WINDOW_SECONDS = 60;

    private const INVALID_DURATION = 'Invalid ISO 8601 duration %s.';

    public static function defaultDriver(): string
    {
        return interface_exists(EntityManagerInterface::class) ? self::DRIVER_DOCTRINE : self::DRIVER_IN_MEMORY;
    }

    public static function define(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode();
        $root
            ->validate()
                ->ifTrue(ConfigurationRules::hasBothCredentialSources(...))
                ->thenInvalid('"credentials" and "credentials_provider" / "platform_resolver" are mutually exclusive.')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::hasNoCredentialSource(...))
                ->thenInvalid('Configure either "credentials" or "credentials_provider" and "platform_resolver".')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::hasIncompleteCredentialServices(...))
                ->thenInvalid('"credentials_provider" and "platform_resolver" must be configured together.')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::lockEnabledWithoutComponent(...))
                ->thenInvalid('"lock.enabled: true" requires symfony/lock (composer require symfony/lock).')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::simulatedGatewayInProduction(...))
                ->thenInvalid('"gateway.simulated" is meant for tests and cannot be used with "environment: production".')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::outboxWithoutStorage(...))
                ->thenInvalid('The "custom" persistence driver requires "persistence.outbox" when the outbox is enabled.')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::schedulerEnabledWithoutComponent(...))
                ->thenInvalid('"scheduler.enabled" requires symfony/scheduler (composer require symfony/scheduler).')
            ->end()
            ->children()
                ->scalarNode('environment')
                    ->isRequired()
                    ->info('"production", "sandbox" or "test_api"; usually "%env(URSSAF_PN_ENV)%" (resolved at runtime).')
                    ->validate()
                        ->ifTrue(ValueRules::isUnknownEnvironment(...))
                        ->thenInvalid('Unknown environment %s: use "production", "sandbox" or "test_api".')
                    ->end()
                ->end()
                ->scalarNode('base_uri')->defaultNull()->info('Overrides the host of the selected environment (a proxy); the base path and the scope still follow the environment.')->end()
                ->enumNode('accept_language')->values(['FR', 'EN'])->defaultValue(ApiConfiguration::DEFAULT_ACCEPT_LANGUAGE)->info('Value of the "Accept-Language" header.')->end()
                ->scalarNode('api_timezone')
                    ->defaultValue(ApiConfiguration::DEFAULT_API_TIME_ZONE)
                    ->info('Time zone of the zone-less timestamps written by URSSAF (ADR 0006).')
                    ->validate()
                        ->ifTrue(ValueRules::isUnknownTimeZone(...))
                        ->thenInvalid('Unknown time zone %s.')
                    ->end()
                ->end()
                ->scalarNode('request_id_header')->defaultNull()->info('Name of the optional request id header (a UUID per attempt); null: not sent.')->end()
                ->integerNode('token_safety_margin')->min(0)->defaultValue(ApiConfiguration::DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS)->info('Seconds before expiry from which an access token is renewed.')->end()
                ->scalarNode('http_client')->defaultNull()->info('Service id of a PSR-18 client (default: a dedicated Symfony HttpClient).')->end()
                ->floatNode('http_timeout')->min(0.1)->defaultValue(15.0)->info('Timeout in seconds of the default HTTP client.')->end()
                ->scalarNode('credentials_provider')->defaultNull()->info('Service id of a CredentialsProvider (with "platform_resolver", instead of "credentials").')->end()
                ->scalarNode('platform_resolver')->defaultNull()->info('Service id of a PlatformResolver (with "credentials_provider", instead of "credentials").')->end()
                ->arrayNode('credentials')
                    ->info('Credentials and platform by tenant id (ADR 0007); use "%env(...)%" placeholders.')
                    ->useAttributeAsKey('tenant_id')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('platform_id')->isRequired()->cannotBeEmpty()->info('Identifier of the platform declared to URSSAF.')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tenants')
                    ->info('Tenants processed by commands that cannot discover them (default: the keys of "credentials").')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('token_store')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cache_pool')->defaultValue('cache.app')->cannotBeEmpty()->info('PSR-6 pool of the access tokens (a pool shared by every process is recommended).')->end()
                        ->scalarNode('service')->defaultNull()->info('Service id of a TokenStore (takes precedence over the cache pool).')->end()
                    ->end()
                ->end()
                ->arrayNode('gateway')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('simulated')->defaultFalse()->info('Tests only: replaces the URSSAF API with the in-memory Testing\\SimulatedPlatformGateway (public service, to script the scenarios).')->end()
                    ->end()
                ->end()
                ->arrayNode('rate_limiter')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->info('Limits the calls to URSSAF per tenant in the current process (URSSAF publishes no quota).')->end()
                        ->integerNode('max_calls')->min(1)->defaultValue(self::DEFAULT_RATE_LIMIT_MAX_CALLS)->info('Calls per tenant within the window.')->end()
                        ->integerNode('window_seconds')->min(1)->defaultValue(self::DEFAULT_RATE_LIMIT_WINDOW_SECONDS)->info('Window length in seconds.')->end()
                    ->end()
                ->end()
                ->arrayNode('lock')
                    ->info('Non-blocking Symfony Lock per operation ("urssaf_pn.<operation>.<scope>"): overlapping runs are skipped (command) or retried later (Messenger).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('enabled')->values(['auto', true, false])->defaultValue('auto')->info('"auto": enabled when symfony/lock is installed and the factory service exists.')->end()
                        ->scalarNode('factory')->defaultValue('lock.factory')->cannotBeEmpty()->info('Service id of a Symfony\\Component\\Lock\\LockFactory.')->end()
                        ->floatNode('ttl')->min(1)->defaultValue(3600.0)->info('Seconds after which a lock of a crashed process expires.')->end()
                    ->end()
                ->end()
                ->append(self::outbox())
                ->append(self::scheduler())
                ->append(self::workflow())
                ->append(self::persistence())
                ->arrayNode('messenger')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->info('Registers the handlers of the tenant synchronisation and reconciliation messages.')->end()
                        ->scalarNode('bus')->defaultValue('messenger.default_bus')->cannotBeEmpty()->info('Bus of the "messenger" outbox sink.')->end()
                    ->end()
                ->end()
            ->end();
    }

    private static function outbox(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('outbox');
        $node
            ->info('Integration events (Contract\\Event) written in the transaction of each state change, then relayed to the host (docs/integration-events.md).')
            ->addDefaultsIfNotSet()
            ->validate()
                ->ifTrue(ConfigurationRules::customSinkWithoutService(...))
                ->thenInvalid('The "custom" outbox sink requires "outbox.service".')
            ->end()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->enumNode('sink')
                    ->values([self::SINK_MESSENGER, self::SINK_EVENT_DISPATCHER, self::SINK_CUSTOM])
                    ->defaultValue(self::SINK_MESSENGER)
                    ->info('"messenger" (bus "messenger.bus"), "event_dispatcher", or "custom" (the IntegrationEventSink "outbox.service").')
                ->end()
                ->scalarNode('service')->defaultNull()->info('Service id of your IntegrationEventSink (sink "custom").')->end()
                ->append(self::duration('retention', 'P30D', 'How long a dispatched entry is kept (urssaf:pn:outbox:purge).'))
            ->end();

        return $node;
    }

    private static function scheduler(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('scheduler');
        $node
            ->info('Symfony Scheduler schedule "urssaf_pn" running the synchronisation and the reconciliation of every configured tenant: an alternative to the crontab.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->append(self::frequency('sync_frequency', 'PT10M', 'urssaf:pn:submissions:sync'))
                ->append(self::frequency('reconcile_frequency', 'PT15M', 'urssaf:pn:submissions:reconcile'))
            ->end();

        return $node;
    }

    private static function frequency(string $task, string $default, string $command): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition($task);
        $node
            ->defaultValue($default)
            ->info(\sprintf('ISO 8601 duration between two runs of %s; null to leave the task out.', $command))
            ->validate()->ifTrue(ValueRules::isInvalidOptionalInterval(...))->thenInvalid(self::INVALID_DURATION)->end();

        return $node;
    }

    private static function duration(string $name, string $default, string $info): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition($name);
        $node
            ->defaultValue($default)
            ->info($info)
            ->validate()->ifTrue(ValueRules::isInvalidInterval(...))->thenInvalid(self::INVALID_DURATION)->end();

        return $node;
    }

    private static function workflow(): ArrayNodeDefinition
    {
        $staleUploadAfter = new ScalarNodeDefinition('stale_upload_after');
        $staleUploadAfter
            ->defaultValue('PT1H')
            ->info('How long a submission may stay "uploading" before it is considered stale and moved to "outcome_unknown" (ADR 0003).')
            ->validate()->ifTrue(ValueRules::isInvalidPositiveInterval(...))->thenInvalid('Invalid ISO 8601 duration %s: it must be greater than zero.')->end();
        $node = new ArrayNodeDefinition('workflow');
        $node
            ->addDefaultsIfNotSet()
            ->validate()
                ->ifTrue(ConfigurationRules::defaultPageAboveMax(...))
                ->thenInvalid('"default_page_size" must not be greater than "max_page_size".')
            ->end()
            ->children()
                ->append(self::duration('status_poll_interval', 'PT10M', 'Interval between two reads of a submission in process (ADR 0004).'))
                ->append(self::duration('payment_poll_interval', 'PT6H', 'Interval between two reads of the payment status of an accepted declaration (ADR 0004).'))
                ->append(self::duration('max_polling_age', 'P1Y', 'How long a submission is still polled after its upload, whatever its status.'))
                ->integerNode('max_upload_attempts')->min(1)->defaultValue(WorkflowPolicy::DEFAULT_MAX_UPLOAD_ATTEMPTS)->info('Upload attempts after which a submission is not uploaded again automatically.')->end()
                ->append($staleUploadAfter)
                ->append(self::duration('manual_review_after', 'P1D', 'How long a submission may stay "outcome_unknown" before it is reported as needing a manual review.'))
                ->integerNode('reconciliation_max_pages')->min(1)->defaultValue(WorkflowPolicy::DEFAULT_RECONCILIATION_MAX_PAGES)->info('Search pages read to reconcile one submission whose outcome is unknown.')->end()
                ->booleanNode('concurrent_declaration_guard')->defaultTrue()->info('Refuses a declaration while another one of the same type is in process (ADR 0008).')->end()
                ->integerNode('default_page_size')->min(1)->defaultValue(WorkflowPolicy::DEFAULT_PAGE_SIZE)->info('Records loaded by a batch use case without limit.')->end()
                ->integerNode('max_page_size')->min(1)->defaultValue(WorkflowPolicy::MAX_PAGE_SIZE)->info('Upper bound of a batch.')->end()
            ->end();

        return $node;
    }

    private static function persistence(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('persistence');
        $node
            ->addDefaultsIfNotSet()
            ->validate()
                ->ifTrue(ConfigurationRules::customDriverWithoutRepository(...))
                ->thenInvalid('The "custom" driver requires "repository".')
            ->end()
            ->children()
                ->enumNode('driver')
                    ->values([self::DRIVER_IN_MEMORY, self::DRIVER_DOCTRINE, self::DRIVER_CUSTOM])
                    ->defaultValue(self::defaultDriver())
                    ->info('"doctrine" when doctrine/orm is installed, else "in_memory" (tests and prototypes).')
                ->end()
                ->scalarNode('entity_manager')->defaultValue('default')->cannotBeEmpty()
                    ->info('Name of the Doctrine entity manager (driver "doctrine"). Use a DEDICATED entity manager (own connection) for this package: its repository flushes the whole unit of work of the entity manager it uses, which would also write pending changes of the host entities sharing it.')
                ->end()
                ->scalarNode('repository')->defaultNull()->info('Service id of your SubmissionRepository (driver "custom").')->end()
                ->scalarNode('statistics')->defaultNull()->info('Service id of your WorkflowStatistics (driver "custom", optional: without it, GetWorkflowOverviewHandler and urssaf:pn:status are not available).')->end()
                ->scalarNode('transaction_manager')->defaultNull()->info('Service id of your TransactionManager shared with the repository (driver "custom"; default: ImmediateTransactionManager).')->end()
                ->scalarNode('outbox')->defaultNull()->info('Service id of your Outbox (required with the "custom" driver when the outbox is enabled; default: the outbox of the driver).')->end()
            ->end();

        return $node;
    }
}
