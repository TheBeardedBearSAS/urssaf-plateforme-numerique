<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

/**
 * Wiring of the HTTP core (token provider and JSON client) on top of a PSR-18 mock client and in-memory fakes.
 */
final readonly class HttpStack
{
    public const ISSUED_VALUE = 'fictitious-access-token-0001';
    public const RENEWED_TOKEN_RESPONSE = '{"access_token":"second-token","expires_in":3600}';

    public Client $http;
    public InMemoryTokenStore $tokens;
    public RecordingRateLimiter $rateLimiter;
    public SteppingClock $clock;
    public CapturingLogger $logger;
    public StaticCredentialsProvider $credentials;
    public Psr17Factory $factory;
    public SequenceIdGenerator $ids;

    public function __construct(public ApiConfiguration $configuration, int $clockStepMicroseconds)
    {
        $this->http = new Client();
        $this->tokens = new InMemoryTokenStore();
        $this->rateLimiter = new RecordingRateLimiter();
        $this->clock = new SteppingClock($clockStepMicroseconds);
        $this->logger = new CapturingLogger();
        $this->credentials = new StaticCredentialsProvider();
        $this->factory = new Psr17Factory();
        $this->ids = new SequenceIdGenerator();
    }

    /**
     * Fetches and caches a token now, so that a later failure queued on the mock client hits the API call itself.
     */
    public function warmUpToken(): void
    {
        $this->respondToken();
        $this->tokenProvider()->accessToken($this->credentials->forTenant(HttpStacks::tenant()), Operation::GetNewsFeed);
        $this->logger->clear();
    }

    public function tokenProvider(): OAuth2TokenProvider
    {
        return new OAuth2TokenProvider(
            $this->http,
            $this->factory,
            $this->factory,
            $this->tokens,
            $this->clock,
            $this->configuration,
            $this->logger,
        );
    }

    public function jsonClient(): JsonApiClient
    {
        return new JsonApiClient(
            $this->http,
            $this->factory,
            $this->factory,
            $this->tokenProvider(),
            $this->rateLimiter,
            $this->ids,
            $this->clock,
            $this->configuration,
            $this->logger,
        );
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return array_values($this->http->getRequests());
    }

    public function lastRequest(): RequestInterface
    {
        $requests = $this->requests();
        $last = array_pop($requests);

        return $last ?? throw new LogicException('No request was sent.');
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(int $status, string $body, array $headers = []): void
    {
        $this->http->addResponse(new Response($status, $headers, $body));
    }

    public function respondToken(): void
    {
        $this->respond(
            200,
            '{"access_token":"' . self::ISSUED_VALUE . '","token_type":"Bearer","expires_in":3600,"scope":"ods.pn.platforms"}',
        );
    }

    public function fail(TransportFailure $exception): void
    {
        $this->http->addException($exception);
    }
}
