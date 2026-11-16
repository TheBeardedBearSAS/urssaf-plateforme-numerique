<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StringFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

final class JsonApiClientLoggingTest extends TestCase
{
    public function testItLogsMethodPathStatusDurationAndRequestId(): void
    {
        $stack = HttpStacks::stepping(250_000);
        $stack->respondToken();
        $stack->respond(200, '{"secret_looking":"fictitious name"}');

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::SearchDeclarations, '/platforms/p-1/declarations', [['search', 'needle']]),
        );

        $records = $stack->logger->records();
        static::assertCount(2, $records);
        static::assertSame('info', $records[1]['level']);
        static::assertSame(
            [
                'method' => 'GET',
                'path' => '/platforms/p-1/declarations',
                'request_id' => 'request-id-1',
                'status' => 200,
                'duration_ms' => 250,
            ],
            $records[1]['context'],
        );
    }

    public function testNeitherSecretsTokensBodiesNorQueriesAreLogged(): void
    {
        $stack = HttpStacks::stepping(1000);
        $stack->respondToken();
        $stack->respond(200, '{"fileName":"fictitious-body-marker"}');

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::multipart(
                Operation::UploadDeclaration,
                '/p/upload',
                [['type', 'DECLARATION'], ['search', 'needle']],
                new StringFileSource(FileName::fromString('DPDE_1.xml.gz'), 'file-content-marker'),
            ),
        );

        $dump = $stack->logger->dump();
        static::assertStringNotContainsString(StaticCredentialsProvider::SHARED_VALUE, $dump);
        static::assertStringNotContainsString(HttpStack::ISSUED_VALUE, $dump);
        static::assertStringNotContainsString('Bearer', $dump);
        static::assertStringNotContainsString('file-content-marker', $dump);
        static::assertStringNotContainsString('fictitious-body-marker', $dump);
        static::assertStringNotContainsString('needle', $dump);
        static::assertStringNotContainsString('DPDE_1', $dump);
    }

    public function testItLogsTransportFailuresAsWarnings(): void
    {
        $stack = HttpStacks::stepping(250_000);
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout'));

        try {
            $stack->jsonClient()->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                ApiRequest::get(Operation::GetIdentification, '/p', []),
            );
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable) {
            $records = $stack->logger->records();
            static::assertCount(1, $records);
            static::assertSame('warning', $records[0]['level']);
            static::assertSame(
                [
                    'method' => 'GET',
                    'path' => '/p',
                    'request_id' => 'request-id-1',
                    'duration_ms' => 250,
                    'exception' => TransportFailure::class,
                ],
                $records[0]['context'],
            );
        }
    }

    public function testAnUploadTransportFailureIsLoggedTheSameWay(): void
    {
        $stack = HttpStacks::stepping(250_000);
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout'));

        try {
            $stack->jsonClient()->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                ApiRequest::multipart(
                    Operation::UploadIdentification,
                    '/p/upload',
                    [],
                    new StringFileSource(FileName::fromString('DPID_1.xml.gz'), 'x'),
                ),
            );
            static::fail('Expected an unknown outcome.');
        } catch (OutcomeUnknown) {
            $records = $stack->logger->records();
            static::assertCount(1, $records);
            static::assertSame('warning', $records[0]['level']);
            static::assertSame(TransportFailure::class, $records[0]['context']['exception']);
        }
    }
}
