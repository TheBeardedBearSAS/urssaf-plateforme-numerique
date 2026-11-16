<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http\HttpStacks;

final class UrssafGatewayFactoryTest extends TestCase
{
    private const ACCESS_ANSWER = '{"access_token":"fictitious-access-token-0001","token_type":"Bearer","expires_in":3600}';

    public function testCreateBuildsAWorkingGatewayWithTheDefaultOptions(): void
    {
        $http = $this->httpClient();
        $tokens = new InMemoryTokenStore();

        $gateway = UrssafGatewayFactory::create(
            $this->transport($http),
            new StaticCredentialsProvider(),
            $tokens,
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        );

        $gateway->getIdentification(HttpStacks::tenant(), PlatformId::fromString('p'), UrssafReference::fromString('r'));

        $requests = array_values($http->getRequests());
        static::assertCount(2, $requests);
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', (string) $requests[0]->getUri());
        static::assertSame(
            'https://api-edi.urssaf.fr/api-odspn-be-platforms/v1/platforms/p/identifications/r',
            (string) $requests[1]->getUri(),
        );
        static::assertSame('Bearer fictitious-access-token-0001', $requests[1]->getHeaderLine('Authorization'));
    }

    public function testTheGivenOptionsAreUsedByTheWholeStack(): void
    {
        $http = $this->httpClient();
        $rateLimiter = new RecordingRateLimiter();
        $logger = new CapturingLogger();

        $gateway = UrssafGatewayFactory::createWith(
            $this->transport($http),
            new StaticCredentialsProvider(),
            new InMemoryTokenStore(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
            GatewayOptions::defaults()
                ->withRateLimiter($rateLimiter)
                ->withIdGenerator(new SequenceIdGenerator())
                ->withClock(new SteppingClock(2_000))
                ->withLogger($logger),
        );

        $gateway->getIdentification(HttpStacks::tenant(), PlatformId::fromString('p'), UrssafReference::fromString('r'));

        static::assertCount(1, $rateLimiter->acquired());
        $records = $logger->records();
        static::assertNotSame([], $records);
        $call = array_values(array_filter($records, static fn(array $record): bool => $record['message'] === 'URSSAF call.'));
        static::assertCount(1, $call);
        static::assertSame('request-id-1', $call[0]['context']['request_id']);
        static::assertSame(2, $call[0]['context']['duration_ms']);
    }

    private function httpClient(): Client
    {
        $http = new Client();
        $http->addResponse(new Response(200, [], self::ACCESS_ANSWER));
        $http->addResponse(new Response(200, [], '{}'));

        return $http;
    }

    private function transport(Client $http): HttpTransport
    {
        $factory = new Psr17Factory();

        return new HttpTransport($http, $factory, $factory);
    }
}
