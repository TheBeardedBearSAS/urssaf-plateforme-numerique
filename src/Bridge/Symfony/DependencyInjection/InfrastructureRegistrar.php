<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\EventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event\Psr14EventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Platform\InMemoryPlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\Psr6TokenStore;

/**
 * Generic infrastructure: clock, identifiers, events, credentials and platforms of the tenants, access token cache,
 * rate limiter.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class InfrastructureRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->services->set(Clock::class, Clock::class)
            ->factory(ClockFactory::class . '::create')
            ->args([new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $this->services->set(IdGenerator::class, RandomUuidGenerator::class)->args([new Definition(SecureRandomBytes::class)]);
        $this->services->set(EventPublisher::class, Psr14EventPublisher::class)->args([new Reference('event_dispatcher')]);
        $this->registerTenants($config['credentials_provider'], $config['platform_resolver'], $config['credentials']);
        $this->registerTokenStore($config['token_store']);
        $this->registerRateLimiter($config['rate_limiter']);
    }

    /**
     * The services of the host (both given together, ConfigurationRules), or the in-memory ones built from the
     * "credentials" option.
     *
     * @param array<string, array{client_id: string, client_secret: string, platform_id: string}> $credentials
     */
    private function registerTenants(?string $provider, ?string $resolver, array $credentials): void
    {
        if ($provider !== null) {
            $this->services->alias(CredentialsProvider::class, $provider);
            $this->services->alias(PlatformResolver::class, (string) $resolver);

            return;
        }
        $secrets = [];
        $platforms = [];
        foreach ($credentials as $tenantId => $tenant) {
            $secrets[$tenantId] = (new Definition(Credentials::class, [$tenant['client_id'], $tenant['client_secret']]))
                ->setFactory([Credentials::class, 'of']);
            $platforms[$tenantId] = (new Definition(PlatformId::class, [$tenant['platform_id']]))
                ->setFactory([PlatformId::class, 'fromString']);
        }
        $this->services->set(CredentialsProvider::class, InMemoryCredentialsProvider::class)->args([$secrets]);
        $this->services->set(PlatformResolver::class, InMemoryPlatformResolver::class)->args([$platforms]);
    }

    /**
     * @param array{cache_pool: string, service: ?string} $tokenStore
     */
    private function registerTokenStore(array $tokenStore): void
    {
        if ($tokenStore['service'] !== null) {
            $this->services->alias(TokenStore::class, $tokenStore['service']);

            return;
        }
        $this->services->set(TokenStore::class, Psr6TokenStore::class)->args([new Reference($tokenStore['cache_pool'])]);
    }

    /**
     * @param array{enabled: bool, max_calls: int, window_seconds: int} $rateLimiter
     */
    private function registerRateLimiter(array $rateLimiter): void
    {
        if ($rateLimiter['enabled']) {
            $this->services->set(RateLimiter::class, SlidingWindowRateLimiter::class)
                ->args([new Reference(Clock::class), $rateLimiter['max_calls'], $rateLimiter['window_seconds']]);

            return;
        }
        $this->services->set(RateLimiter::class, NoopRateLimiter::class);
    }
}
