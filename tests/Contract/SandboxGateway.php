<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Contract;

use Nyholm\Psr7\Factory\Psr17Factory;
use SensitiveParameter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\PlatformExchange;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafHttpGateway;

/**
 * Wires the real HTTP gateway against one URSSAF target from environment variables:
 *
 * - the real-exchange sandbox "api-edi" (`Environment::Sandbox`): URSSAF_SANDBOX_CLIENT_ID /
 *   URSSAF_SANDBOX_CLIENT_SECRET (required, otherwise the suite is skipped), URSSAF_SANDBOX_PLATFORM_ID and
 *   URSSAF_SANDBOX_BASE_URI (optional host override);
 * - the technical test API "Bac a sable" (`Environment::TestApi`): URSSAF_TEST_API_CLIENT_ID /
 *   URSSAF_TEST_API_CLIENT_SECRET / URSSAF_TEST_API_PLATFORM_ID.
 *
 * The platform id is null when its variable is missing: the tests that need one are then skipped.
 */
final readonly class SandboxGateway
{
    public const TENANT = 'contract-tests';

    private function __construct(
        public UrssafHttpGateway $gateway,
        public JsonApiClient $client,
        public OAuth2TokenProvider $tokenProvider,
        public Credentials $credentials,
        public TenantId $tenantId,
        public ?PlatformId $platformId,
    ) {}

    /**
     * Null when the required environment variables are missing.
     */
    public static function sandboxFromEnvironment(): ?self
    {
        return self::build(
            Environment::Sandbox,
            self::env('URSSAF_SANDBOX_BASE_URI'),
            self::env('URSSAF_SANDBOX_CLIENT_ID'),
            self::env('URSSAF_SANDBOX_CLIENT_SECRET'),
            self::env('URSSAF_SANDBOX_PLATFORM_ID'),
        );
    }

    /**
     * Null when the required environment variables are missing.
     */
    public static function testApiFromEnvironment(): ?self
    {
        return self::build(
            Environment::TestApi,
            null,
            self::env('URSSAF_TEST_API_CLIENT_ID'),
            self::env('URSSAF_TEST_API_CLIENT_SECRET'),
            self::env('URSSAF_TEST_API_PLATFORM_ID'),
        );
    }

    public static function env(string $name): ?string
    {
        $value = getenv($name);

        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function build(
        Environment $environment,
        ?string $baseUri,
        ?string $clientId,
        #[SensitiveParameter]
        ?string $clientSecret,
        ?string $platformId,
    ): ?self {
        if ($clientId === null || $clientSecret === null) {
            return null;
        }

        $tenantId = TenantId::fromString(self::TENANT);
        $credentials = Credentials::of($clientId, $clientSecret);
        $configuration = new ApiConfiguration($environment, $baseUri, null);

        $factory = new Psr17Factory();
        $httpClient = new Psr18Client(HttpClient::create(['timeout' => 30]), $factory, $factory);
        $clock = new SystemClock(new NativeClock());
        $tokenProvider = new OAuth2TokenProvider(
            $httpClient,
            $factory,
            $factory,
            new InMemoryTokenStore(),
            $clock,
            $configuration,
        );
        $client = new JsonApiClient(
            $httpClient,
            $factory,
            $factory,
            $tokenProvider,
            new NoopRateLimiter(),
            new RandomUuidGenerator(new SecureRandomBytes()),
            $clock,
            $configuration,
        );

        return new self(
            new UrssafHttpGateway(
                new PlatformExchange(new InMemoryCredentialsProvider([$tenantId->value() => $credentials]), $client),
                $configuration,
            ),
            $client,
            $tokenProvider,
            $credentials,
            $tenantId,
            $platformId === null ? null : PlatformId::fromString($platformId),
        );
    }
}
