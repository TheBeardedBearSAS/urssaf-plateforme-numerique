<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StringFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\UnreadableFileSource;

final class JsonApiClientRequestTest extends TestCase
{
    public function testItSendsAnAuthenticatedJsonGetRequest(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"ok":"yes"}');

        $response = $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::GetDeclaration, '/platforms/p-1/declarations/ref-1', []),
        );

        static::assertSame('yes', $response->payload()->string('ok'));
        static::assertFalse($response->isStream());
        static::assertSame('request-id-1', $response->requestId());
        $request = $stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/api-odspn-be-platforms/v1/platforms/p-1/declarations/ref-1',
            (string) $request->getUri(),
        );
        static::assertSame('Bearer ' . HttpStack::ISSUED_VALUE, $request->getHeaderLine('Authorization'));
        static::assertSame('application/json', $request->getHeaderLine('Accept'));
        static::assertSame('FR', $request->getHeaderLine('Accept-Language'));
        static::assertSame(ApiConfiguration::DEFAULT_USER_AGENT, $request->getHeaderLine('User-Agent'));
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertFalse($request->hasHeader('x-request-id'));
        static::assertSame('', (string) $request->getBody());
    }

    public function testItSendsTheConfiguredLanguageUserAgentAndRequestIdHeader(): void
    {
        $stack = HttpStacks::configured(
            new ApiConfiguration(Environment::Production, null, 'X-Request-Id', 'EN', 60, 'my-agent'),
        );
        $stack->respondToken();
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::GetNewsFeed, '/platforms/p-1/news-feed', []),
        );

        $request = $stack->lastRequest();
        static::assertSame('https://api.urssaf.fr/api-odspn-be-platforms/v1/platforms/p-1/news-feed', (string) $request->getUri());
        static::assertSame('EN', $request->getHeaderLine('Accept-Language'));
        static::assertSame('my-agent', $request->getHeaderLine('User-Agent'));
        static::assertSame('request-id-1', $request->getHeaderLine('X-Request-Id'));
    }

    public function testItTargetsTheTestApiBasePath(): void
    {
        $stack = HttpStacks::configured(ApiConfiguration::forEnvironment(Environment::TestApi));
        $stack->respondToken();
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::GetIdentification, '/platforms/p-1/identifications/r', []),
        );

        static::assertSame(
            'https://api.urssaf.fr/api-odspn-be-platforms-sandbox/v1/platforms/p-1/identifications/r',
            (string) $stack->lastRequest()->getUri(),
        );
    }

    public function testItEncodesTheQueryAccordingToRfc3986AndRepeatsTheSortParameter(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '[]');

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::SearchDeclarations, '/p', [
                ['search', 'a b&c'],
                ['sort', 'period:ASC'],
                ['sort', 'lastUpdateDate:DESC'],
            ]),
        );

        static::assertSame(
            'search=a%20b%26c&sort=period%3AASC&sort=lastUpdateDate%3ADESC',
            $stack->lastRequest()->getUri()->getQuery(),
        );
    }

    public function testItSendsAFileAsAMultipartBodyWithItsQuery(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"urssafRef":"r"}');
        $file = new StringFileSource(FileName::fromString('DPDE_1.xml.gz'), "\x1f\x8b\x00data");

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::multipart(Operation::UploadDeclaration, '/platforms/p-1/declarations/upload', [['type', 'DPDE']], $file),
        );

        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/api-odspn-be-platforms/v1/platforms/p-1/declarations/upload?type=DPDE',
            (string) $request->getUri(),
        );
        static::assertSame('application/json', $request->getHeaderLine('Accept'));
        static::assertSame('multipart/form-data; boundary=request-id-2', $request->getHeaderLine('Content-Type'));
        static::assertSame(
            "--request-id-2\r\n"
            . "Content-Disposition: form-data; name=\"name\"\r\n\r\nDPDE_1.xml.gz\r\n"
            . "--request-id-2\r\n"
            . "Content-Disposition: form-data; name=\"data\"; filename=\"DPDE_1.xml.gz\"\r\n"
            . "Content-Type: application/octet-stream\r\n\r\n\x1f\x8b\x00data\r\n"
            . "--request-id-2--\r\n",
            (string) $request->getBody(),
        );
        static::assertSame(1, $file->timesOpened());
    }

    public function testTheFileIsClosedEvenWhenItCannotBeRead(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $file = new UnreadableFileSource();
        $failed = false;

        try {
            $stack->jsonClient()->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                ApiRequest::multipart(Operation::UploadIdentification, '/p/upload', [], $file),
            );
        } catch (RuntimeException) {
            $failed = true;
        }

        static::assertTrue($failed);
        static::assertFalse($file->isStillOpen());
    }

    public function testADownloadAcceptsABinaryOrAJsonError(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, 'bytes');

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::download(Operation::DownloadDeclarationFile, '/platforms/p-1/declarations/r/file/f'),
        );

        $request = $stack->lastRequest();
        static::assertSame('application/octet-stream, application/json', $request->getHeaderLine('Accept'));
        static::assertFalse($request->hasHeader('Content-Type'));
    }

    public function testItAcquiresTheRateLimiterForTheOperationOfEachCall(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{}');
        $stack->respond(200, '{}');
        $client = $stack->jsonClient();
        $credentials = $stack->credentials->forTenant(HttpStacks::tenant());

        $client->send(HttpStacks::tenant(), $credentials, ApiRequest::get(Operation::GetIdentification, '/p', []));
        $client->send(HttpStacks::tenant(), $credentials, ApiRequest::get(Operation::SearchIdentifications, '/q', []));

        static::assertSame(
            ['tenant-1:getPlatformIdentification', 'tenant-1:searchPlatformIdentification'],
            $stack->rateLimiter->acquired(),
        );
    }

    public function testAnExceededRateLimitStopsBeforeAnyHttpCall(): void
    {
        $stack = HttpStacks::sandbox();
        $client = new JsonApiClient(
            $stack->http,
            $stack->factory,
            $stack->factory,
            $stack->tokenProvider(),
            new RejectingRateLimiter(),
            new SequenceIdGenerator(),
            $stack->clock,
            $stack->configuration,
        );

        try {
            $client->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                ApiRequest::get(Operation::GetIdentification, '/p', []),
            );
            static::fail('Expected the rate limit to be enforced.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(30, $exception->retryAfterSeconds());
        }
        static::assertSame([], $stack->requests());
    }
}
