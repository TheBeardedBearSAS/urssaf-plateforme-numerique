<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SensitiveParameter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\QueryEncoder;

/**
 * Authenticated transport over PSR-18 (ADR 0002 and 0003). Build the gateway with
 * {@see \TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafGatewayFactory}.
 *
 * Per attempt: rate limiter, bearer token, `Accept`, `Accept-Language`, `User-Agent` and the optional request id header
 * (a fresh id per attempt). A 401 invalidates the cached token and the call is replayed exactly once (a multipart
 * file is opened again). Statuses are then mapped to the gateway exceptions; the two uploads never resend blindly:
 * a transport error, any 5xx or an unreadable 2xx answer is an {@see OutcomeUnknown}.
 * Neither the token nor request/response bodies nor query strings are logged.
 *
 * @internal
 */
final readonly class JsonApiClient
{
    private ResponseInterpreter $interpreter;

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private OAuth2TokenProvider $tokenProvider,
        private RateLimiter $rateLimiter,
        private IdGenerator $idGenerator,
        private Clock $clock,
        private ApiConfiguration $configuration,
        private LoggerInterface $logger = new NullLogger(),
        ErrorPayloadParser $errorParser = new ErrorPayloadParser(),
    ) {
        $this->interpreter = new ResponseInterpreter($streamFactory, $errorParser);
    }

    /**
     * @throws RateLimitExceeded          the local rate limiter refused the call (nothing sent)
     * @throws UrssafRequestRejected      HTTP 400
     * @throws UrssafAuthenticationFailed HTTP 401 (after the replay), 403, or the token request refused
     * @throws UrssafResourceNotFound     HTTP 404 (not on an upload)
     * @throws UrssafUnavailable          HTTP 429, 5xx (reads), transport error (reads), token request unavailable
     * @throws OutcomeUnknown             upload: transport error, 5xx or unreadable 2xx answer
     * @throws UnexpectedUrssafResponse   any other status or unreadable body (reads)
     */
    public function send(TenantId $tenantId, Credentials $credentials, ApiRequest $request): ApiResponse
    {
        [$response, $requestId] = $this->attempt($tenantId, $credentials, $request);

        if ($response->getStatusCode() === 401) {
            $this->tokenProvider->invalidate($credentials);
            [$response, $requestId] = $this->attempt($tenantId, $credentials, $request);
        }

        return $this->interpreter->interpret($request, $response, $requestId);
    }

    /**
     * @return array{ResponseInterface, string} the response and the request id
     */
    private function attempt(TenantId $tenantId, Credentials $credentials, ApiRequest $request): array
    {
        $operation = $request->operation();
        $this->rateLimiter->acquire($tenantId, $operation);

        $requestId = $this->idGenerator->generate();
        $httpRequest = $this->buildRequest(
            $request,
            $this->tokenProvider->accessToken($credentials, $operation),
            $requestId,
        );
        $context = ['method' => $request->method(), 'path' => $request->path(), 'request_id' => $requestId];
        $startedAt = $this->clock->now();

        try {
            $response = $this->httpClient->sendRequest($httpRequest);
        } catch (ClientExceptionInterface $exception) {
            $this->logger->warning('URSSAF call failed: transport error.', $context + [
                'duration_ms' => Duration::millisecondsSince($startedAt, $this->clock->now()),
                'exception' => $exception::class,
            ]);

            throw $operation->isUpload()
                ? OutcomeUnknown::afterTransportError($operation, $exception::class)
                : UrssafUnavailable::dueToTransportError($operation, $exception::class);
        }

        $this->logger->info('URSSAF call.', $context + [
            'status' => $response->getStatusCode(),
            'duration_ms' => Duration::millisecondsSince($startedAt, $this->clock->now()),
        ]);

        return [$response, $requestId];
    }

    private function buildRequest(
        ApiRequest $request,
        #[SensitiveParameter]
        string $token,
        string $requestId,
    ): RequestInterface {
        $uri = $this->configuration->apiUri($request->path());
        if ($request->query() !== []) {
            $uri .= '?' . QueryEncoder::toQueryString($request->query());
        }

        $httpRequest = $this->requestFactory
            ->createRequest($request->method(), $uri)
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept', $request->isDownload() ? 'application/octet-stream, application/json' : 'application/json')
            ->withHeader('Accept-Language', $this->configuration->acceptLanguage())
            ->withHeader('User-Agent', $this->configuration->userAgent());

        $requestIdHeader = $this->configuration->requestIdHeader();
        if ($requestIdHeader !== null) {
            $httpRequest = $httpRequest->withHeader($requestIdHeader, $requestId);
        }

        $file = $request->file();

        return $file instanceof FileSource ? $this->withMultipartBody($httpRequest, $file) : $httpRequest;
    }

    private function withMultipartBody(RequestInterface $httpRequest, FileSource $file): RequestInterface
    {
        $content = $file->open();

        try {
            [$body, $contentType] = (new MultipartBody($this->streamFactory))->build(
                $file->name(),
                $content,
                $this->idGenerator->generate(),
            );
        } finally {
            fclose($content);
        }

        return $httpRequest->withHeader('Content-Type', $contentType)->withBody($body);
    }
}
