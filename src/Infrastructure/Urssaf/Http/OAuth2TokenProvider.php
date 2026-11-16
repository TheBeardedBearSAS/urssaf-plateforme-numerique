<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http;

use DateInterval;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\AccessToken;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\CacheKey;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafEndpoints;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\JsonDecoder;

/**
 * OAuth2 client-credentials flow with a shared token cache. Build the gateway with
 * {@see \TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafGatewayFactory}.
 *
 * The cache key is `CacheKey::of('urssaf_pn.' . sha256(environment|baseUri|scope|clientId))` (ADR 0002): safe for
 * PSR-6 / PSR-16 and never containing the secret. The scope is sent in the form body. Nothing secret (client secret,
 * token) is ever logged.
 *
 * @internal
 */
final readonly class OAuth2TokenProvider
{
    private const KEY_PREFIX = 'urssaf_pn.';

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private TokenStore $tokenStore,
        private Clock $clock,
        private ApiConfiguration $configuration,
        private LoggerInterface $logger = new NullLogger(),
        private ErrorPayloadParser $errorParser = new ErrorPayloadParser(),
    ) {}

    /**
     * Returns a valid bearer token, fetching a new one when the cached one is missing or about to expire.
     *
     * @throws UrssafAuthenticationFailed token endpoint answered 400/401/403
     * @throws UrssafUnavailable          token endpoint answered 429/5xx or could not be reached (the API call itself
     *                                    is not sent: {@see UrssafUnavailable::mayHaveReachedUrssaf()} is false)
     * @throws UnexpectedUrssafResponse
     */
    public function accessToken(Credentials $credentials, Operation $operation): string
    {
        $key = $this->cacheKey($credentials);
        $cached = $this->tokenStore->get($key);

        if ($cached instanceof AccessToken && !$cached->isExpired(
            $this->clock->now(),
            $this->configuration->tokenSafetyMarginSeconds(),
        )) {
            return $cached->value();
        }

        $token = $this->fetch($credentials, $operation);
        $this->tokenStore->save($key, $token);

        return $token->value();
    }

    public function invalidate(Credentials $credentials): void
    {
        $this->tokenStore->delete($this->cacheKey($credentials));
    }

    private function cacheKey(Credentials $credentials): string
    {
        return CacheKey::of(self::KEY_PREFIX . hash('sha256', \sprintf(
            '%s|%s|%s|%s',
            $this->configuration->environment()->value,
            $this->configuration->baseUri(),
            $this->configuration->scope(),
            $credentials->clientId(),
        )));
    }

    private function fetch(Credentials $credentials, Operation $operation): AccessToken
    {
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $credentials->clientId(),
            'client_secret' => $credentials->clientSecret(),
            'scope' => $this->configuration->scope(),
        ], '', '&', PHP_QUERY_RFC3986);

        $request = $this->requestFactory
            ->createRequest('POST', $this->configuration->tokenUri())
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->configuration->userAgent())
            ->withBody($this->streamFactory->createStream($body));

        $startedAt = $this->clock->now();

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            $this->logger->warning('URSSAF token request failed: transport error.', ['exception' => $exception::class]);

            throw self::transportFailure($operation, $exception);
        }

        $this->logger->info('URSSAF token request.', [
            'method' => 'POST',
            'path' => UrssafEndpoints::TOKEN_PATH,
            'status' => $response->getStatusCode(),
            'duration_ms' => Duration::millisecondsSince($startedAt, $this->clock->now()),
        ]);

        return $this->tokenFrom($response, $operation);
    }

    /**
     * Only the class name of the transport exception is kept: its message embeds the URI (ADR 0002).
     */
    private static function transportFailure(Operation $operation, ClientExceptionInterface $exception): UrssafUnavailable
    {
        return UrssafUnavailable::whileAuthenticating($operation, null, null, $exception::class, ApiErrors::none());
    }

    private function tokenFrom(ResponseInterface $response, Operation $operation): AccessToken
    {
        $status = $response->getStatusCode();

        if (\in_array($status, [400, 401, 403], true)) {
            throw UrssafAuthenticationFailed::withStatus($status);
        }

        if ($status === 429 || $status >= 500) {
            throw UrssafUnavailable::whileAuthenticating(
                $operation,
                $status,
                RetryAfter::seconds($response),
                null,
                $this->errorParser->parseBody((string) $response->getBody()),
            );
        }

        if ($status < 200 || $status >= 300) {
            throw UnexpectedUrssafResponse::unexpectedStatus($operation, $status);
        }

        $payload = JsonDecoder::decode((string) $response->getBody());
        $lifetime = $payload->number('expires_in');
        if ($lifetime === null || (int) $lifetime <= 0) {
            throw UnexpectedUrssafResponse::invalidPayload('field "expires_in" is missing or not positive');
        }

        return AccessToken::of(
            $payload->requireString('access_token'),
            $this->clock->now()->add(new DateInterval('PT' . (int) $lifetime . 'S')),
        );
    }
}
