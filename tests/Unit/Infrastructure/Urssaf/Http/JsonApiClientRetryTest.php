<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StringFileSource;

final class JsonApiClientRetryTest extends TestCase
{
    public function testA401InvalidatesTheTokenAndReplaysTheCallExactlyOnce(): void
    {
        $stack = HttpStacks::configured(new ApiConfiguration(Environment::Sandbox, null, 'X-Request-Id'));
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, HttpStack::RENEWED_TOKEN_RESPONSE);
        $stack->respond(200, '{"ok":"retried"}');

        $response = $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::GetDeclaration, '/p', []),
        );

        static::assertSame('retried', $response->payload()->string('ok'));
        static::assertSame('request-id-2', $response->requestId());
        $requests = $stack->requests();
        static::assertCount(4, $requests);
        static::assertSame('Bearer ' . HttpStack::ISSUED_VALUE, $requests[1]->getHeaderLine('Authorization'));
        static::assertSame('Bearer second-token', $requests[3]->getHeaderLine('Authorization'));
        static::assertSame('request-id-1', $requests[1]->getHeaderLine('X-Request-Id'));
        static::assertSame('request-id-2', $requests[3]->getHeaderLine('X-Request-Id'));
    }

    public function testTheReplayAcquiresTheRateLimiterAgain(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, HttpStack::RENEWED_TOKEN_RESPONSE);
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::GetDeclaration, '/p', []),
        );

        static::assertCount(2, $stack->rateLimiter->acquired());
    }

    public function testAnUploadReplayedAfterA401OpensTheFileAgainAndResendsTheSameBody(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, HttpStack::RENEWED_TOKEN_RESPONSE);
        $stack->respond(200, '{"urssafRef":"r"}');
        $file = new StringFileSource(FileName::fromString('DPID_1.xml.gz'), 'file-bytes');

        $response = $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::multipart(Operation::UploadIdentification, '/p/upload', [], $file),
        );

        static::assertSame('r', $response->payload()->string('urssafRef'));
        static::assertSame(2, $file->timesOpened());
        $requests = $stack->requests();
        static::assertCount(4, $requests);
        static::assertStringContainsString('file-bytes', (string) $requests[1]->getBody());
        static::assertStringContainsString('file-bytes', (string) $requests[3]->getBody());
        static::assertSame('multipart/form-data; boundary=request-id-2', $requests[1]->getHeaderLine('Content-Type'));
        static::assertSame('multipart/form-data; boundary=request-id-4', $requests[3]->getHeaderLine('Content-Type'));
    }

    public function testASecond401FailsAuthenticationWithoutAThirdAttempt(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, HttpStack::RENEWED_TOKEN_RESPONSE);
        $stack->respond(401, '');

        try {
            $stack->jsonClient()->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                ApiRequest::get(Operation::GetDeclaration, '/p', []),
            );
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame(401, $exception->httpStatus());
        }
        static::assertCount(4, $stack->requests());
    }

    public function testOtherStatusesAreNeverReplayed(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(403, '');

        try {
            $stack->jsonClient()->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                ApiRequest::get(Operation::GetDeclaration, '/p', []),
            );
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame(403, $exception->httpStatus());
        }
        static::assertCount(2, $stack->requests());
        static::assertCount(1, $stack->tokens->keys());
    }
}
