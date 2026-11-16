<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\PlatformExchange;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;

/**
 * The definitions of the HTTP gateway, as written by the registrars (nothing resolved nor removed).
 */
final class HttpDefinitionsTest extends TestCase
{
    public function testTheDedicatedHttpClientUsesTheConfiguredTimeout(): void
    {
        $container = (new TestKernel(['http_timeout' => 5.5]))->definitions();

        $http = $container->getDefinition(ServiceIds::SYMFONY_HTTP_CLIENT);
        static::assertSame([HttpClient::class, 'create'], $http->getFactory());
        static::assertSame([['timeout' => 5.5]], $http->getArguments());
        $psr18 = $container->getDefinition(ServiceIds::PSR18_CLIENT);
        static::assertSame(Psr18Client::class, $psr18->getClass());
        static::assertEquals([new Reference(ServiceIds::SYMFONY_HTTP_CLIENT)], $psr18->getArguments());
    }

    public function testTheDedicatedHttpClientTimeoutDefaultsToFifteenSeconds(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertSame([['timeout' => 15.0]], $container->getDefinition(ServiceIds::SYMFONY_HTTP_CLIENT)->getArguments());
    }

    public function testTheServicesThatLogUseThePackageChannel(): void
    {
        $container = (new TestKernel())->definitions();

        foreach ([ServiceIds::TOKEN_PROVIDER, ServiceIds::PROBE_TOKEN_PROVIDER, ServiceIds::JSON_CLIENT, FailureReporter::class] as $id) {
            static::assertSame(['monolog.logger' => [['channel' => 'urssaf_pn']]], $container->getDefinition($id)->getTags(), $id);
        }
    }

    public function testTheFailureReporterReceivesTheOptionalPackageLogger(): void
    {
        $reporter = (new TestKernel())->definitions()->getDefinition(FailureReporter::class);

        static::assertSame(PsrLoggerFailureReporter::class, $reporter->getClass());
        static::assertEquals([new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)], $reporter->getArguments());
    }

    public function testTheApiConfigurationReadsTheEnvironmentAtRuntime(): void
    {
        $arguments = (new TestKernel())->definitions()->getDefinition(ServiceIds::API_CONFIGURATION)->getArguments();

        static::assertCount(7, $arguments);
        static::assertInstanceOf(Definition::class, $arguments[0]);
        static::assertSame(['sandbox'], $arguments[0]->getArguments());
        static::assertEquals(new Definition(DateTimeZone::class, ['Europe/Paris']), $arguments[6]);
    }

    public function testTheTokenProvidersShareTheClientButNotTheStore(): void
    {
        $container = (new TestKernel())->definitions();
        $client = new Reference('test.http_client');
        $factories = new Reference(ServiceIds::PSR18_CLIENT);
        $logger = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        $tail = [new Reference(Clock::class), new Reference(ServiceIds::API_CONFIGURATION), $logger];

        $provider = $container->getDefinition(ServiceIds::TOKEN_PROVIDER);
        $probe = $container->getDefinition(ServiceIds::PROBE_TOKEN_PROVIDER);

        static::assertSame(OAuth2TokenProvider::class, $provider->getClass());
        static::assertSame(OAuth2TokenProvider::class, $probe->getClass());
        static::assertEquals([$client, $factories, $factories, new Reference(TokenStore::class), ...$tail], $provider->getArguments());
        static::assertEquals([$client, $factories, $factories, new Definition(InMemoryTokenStore::class), ...$tail], $probe->getArguments());
    }

    public function testTheJsonClientAndTheGateway(): void
    {
        $container = (new TestKernel())->definitions();
        $factories = new Reference(ServiceIds::PSR18_CLIENT);

        $client = $container->getDefinition(ServiceIds::JSON_CLIENT);
        static::assertSame(JsonApiClient::class, $client->getClass());
        static::assertEquals([
            new Reference('test.http_client'),
            $factories,
            $factories,
            new Reference(ServiceIds::TOKEN_PROVIDER),
            new Reference(RateLimiter::class),
            new Reference(IdGenerator::class),
            new Reference(Clock::class),
            new Reference(ServiceIds::API_CONFIGURATION),
            new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ], $client->getArguments());
        $exchange = $container->getDefinition(ServiceIds::PLATFORM_EXCHANGE);
        static::assertSame(PlatformExchange::class, $exchange->getClass());
        static::assertEquals([new Reference(CredentialsProvider::class), new Reference(ServiceIds::JSON_CLIENT)], $exchange->getArguments());
        $gateway = $container->getDefinition(PlatformGateway::class);
        static::assertSame(UrssafHttpGateway::class, $gateway->getClass());
        static::assertEquals([new Reference(ServiceIds::PLATFORM_EXCHANGE), new Reference(ServiceIds::API_CONFIGURATION)], $gateway->getArguments());
    }

    public function testTheDedicatedPsr18ClientIsTheDefault(): void
    {
        $container = (new TestKernel(['http_client' => null]))->definitions();

        static::assertEquals(new Reference(ServiceIds::PSR18_CLIENT), $container->getDefinition(ServiceIds::JSON_CLIENT)->getArgument(0));
        static::assertEquals(new Reference(ServiceIds::PSR18_CLIENT), $container->getDefinition(ServiceIds::TOKEN_PROVIDER)->getArgument(0));
    }
}
