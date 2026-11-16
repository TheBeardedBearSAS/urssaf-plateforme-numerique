<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection;

use DateTimeZone;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\PlatformExchange;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway;

/**
 * The URSSAF HTTP gateway and everything it needs: PSR-18 client, PSR-17 factories, access tokens, JSON client.
 *
 * It is wired piece by piece (not through UrssafGatewayFactory) to expose the probe token provider of
 * "urssaf:pn:check"; the arguments are those of UrssafGatewayFactory::createWith().
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class GatewayRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->services->set(ServiceIds::SYMFONY_HTTP_CLIENT, HttpClientInterface::class)
            ->factory(HttpClient::class . '::create')
            ->args([['timeout' => $config['http_timeout']]]);
        // Also provides the PSR-17 request and stream factories, whatever the PSR-18 client in use.
        $this->services->set(ServiceIds::PSR18_CLIENT, Psr18Client::class)->args([new Reference(ServiceIds::SYMFONY_HTTP_CLIENT)]);
        $this->services->set(ServiceIds::API_CONFIGURATION, ApiConfiguration::class)->args([
            // Resolved at runtime: the environment is usually an "%env(URSSAF_PN_ENV)%" placeholder.
            (new Definition(Environment::class, [$config['environment']]))->setFactory([Environment::class, 'from']),
            $config['base_uri'],
            $config['request_id_header'],
            $config['accept_language'],
            $config['token_safety_margin'],
            ApiConfiguration::DEFAULT_USER_AGENT,
            new Definition(DateTimeZone::class, [$config['api_timezone']]),
        ]);

        $client = new Reference($config['http_client'] ?? ServiceIds::PSR18_CLIENT);
        $factories = new Reference(ServiceIds::PSR18_CLIENT);
        $logger = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        foreach ([ServiceIds::TOKEN_PROVIDER => new Reference(TokenStore::class), ServiceIds::PROBE_TOKEN_PROVIDER => new Definition(InMemoryTokenStore::class)] as $id => $tokenStore) {
            $this->services->set($id, OAuth2TokenProvider::class)
                ->args([
                    $client,
                    $factories,
                    $factories,
                    $tokenStore,
                    new Reference(Clock::class),
                    new Reference(ServiceIds::API_CONFIGURATION),
                    $logger,
                ])
                ->tag(ServiceIds::LOGGER_TAG, ['channel' => ServiceIds::LOGGER_CHANNEL]);
        }
        $this->services->set(ServiceIds::JSON_CLIENT, JsonApiClient::class)
            ->args([
                $client,
                $factories,
                $factories,
                new Reference(ServiceIds::TOKEN_PROVIDER),
                new Reference(RateLimiter::class),
                new Reference(IdGenerator::class),
                new Reference(Clock::class),
                new Reference(ServiceIds::API_CONFIGURATION),
                $logger,
            ])
            ->tag(ServiceIds::LOGGER_TAG, ['channel' => ServiceIds::LOGGER_CHANNEL]);
        $this->services->set(ServiceIds::PLATFORM_EXCHANGE, PlatformExchange::class)
            ->args([new Reference(CredentialsProvider::class), new Reference(ServiceIds::JSON_CLIENT)]);

        if ($config['gateway']['simulated']) {
            $this->services->set(PlatformGateway::class, SimulatedPlatformGateway::class)
                ->factory(SimulatedGatewayFactory::class . '::create')
                ->args([new Reference(Clock::class), $config['environment']]);
            $this->services->alias(SimulatedPlatformGateway::class, PlatformGateway::class)->public();

            return;
        }
        $this->services->set(PlatformGateway::class, UrssafHttpGateway::class)
            ->args([new Reference(ServiceIds::PLATFORM_EXCHANGE), new Reference(ServiceIds::API_CONFIGURATION)]);
    }
}
