<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;

/**
 * Validation predicates of the configuration tree that read several options (each one is true when the configuration
 * is invalid); the predicates of a single value are in {@see ValueRules}.
 *
 * One small predicate per rule of the tree, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class ConfigurationRules
{
    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasBothCredentialSources(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string, platform_resolver: ?string} $config */
        $config = $value;

        return $config['credentials'] !== [] && ($config['credentials_provider'] !== null || $config['platform_resolver'] !== null);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasNoCredentialSource(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string, platform_resolver: ?string} $config */
        $config = $value;

        return $config['credentials'] === [] && $config['credentials_provider'] === null && $config['platform_resolver'] === null;
    }

    /**
     * The credentials provider and the platform resolver of the host come together (ADR 0007).
     *
     * @param array<array-key, mixed> $value
     */
    public static function hasIncompleteCredentialServices(array $value): bool
    {
        /** @var array{credentials_provider: ?string, platform_resolver: ?string} $config */
        $config = $value;

        $hasProvider = $config['credentials_provider'] !== null;
        $hasResolver = $config['platform_resolver'] !== null;

        return $hasProvider !== $hasResolver;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function simulatedGatewayInProduction(array $value): bool
    {
        /** @var array{environment: string, gateway: array{simulated: bool}} $config */
        $config = $value;

        return $config['gateway']['simulated'] && $config['environment'] === Environment::Production->value;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function lockEnabledWithoutComponent(array $value): bool
    {
        return self::lockRequiredButMissing($value, class_exists(LockFactory::class));
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function lockRequiredButMissing(array $value, bool $componentInstalled): bool
    {
        /** @var array{lock: array{enabled: bool|string}} $config */
        $config = $value;

        return $config['lock']['enabled'] === true && !$componentInstalled;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function schedulerEnabledWithoutComponent(array $value): bool
    {
        return self::schedulerRequiredButMissing($value, interface_exists(ScheduleProviderInterface::class));
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function schedulerRequiredButMissing(array $value, bool $componentInstalled): bool
    {
        /** @var array{scheduler: array{enabled: bool}} $config */
        $config = $value;

        return $config['scheduler']['enabled'] && !$componentInstalled;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function outboxWithoutStorage(array $value): bool
    {
        /** @var array{outbox: array{enabled: bool}, persistence: array{driver: string, outbox: ?string}} $config */
        $config = $value;

        return $config['outbox']['enabled'] && $config['persistence']['outbox'] === null
            && $config['persistence']['driver'] === BundleConfiguration::DRIVER_CUSTOM;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function customSinkWithoutService(array $value): bool
    {
        /** @var array{enabled: bool, sink: string, service: ?string} $outbox */
        $outbox = $value;

        return $outbox['enabled'] && $outbox['sink'] === BundleConfiguration::SINK_CUSTOM && $outbox['service'] === null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function defaultPageAboveMax(array $value): bool
    {
        /** @var array{default_page_size: int, max_page_size: int} $workflow */
        $workflow = $value;

        return $workflow['default_page_size'] > $workflow['max_page_size'];
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function customDriverWithoutRepository(array $value): bool
    {
        /** @var array{driver: string, repository: ?string} $persistence */
        $persistence = $value;

        return $persistence['driver'] === BundleConfiguration::DRIVER_CUSTOM && $persistence['repository'] === null;
    }
}
