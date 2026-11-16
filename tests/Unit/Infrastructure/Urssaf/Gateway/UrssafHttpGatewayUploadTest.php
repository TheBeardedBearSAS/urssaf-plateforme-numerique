<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\InMemoryFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http\HttpStacks;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

final class UrssafHttpGatewayUploadTest extends TestCase
{
    public function testAnIdentificationIsPostedAsMultipartAndItsReferenceReturned(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, WireFixtures::load('upload_response'));

        $reference = $harness->gateway->uploadIdentification(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            InMemoryFileSource::of(FileName::fromString('DPID_202505_24001_000_20250428150823.xml.gz'), 'file-bytes'),
        );

        static::assertSame('cbcf2700-110d-4abf-9835-a9b46e68d7c4', $reference->value());
        $request = $harness->stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame(GatewayHarness::BASE . '/platforms/platform-1/identifications/upload', (string) $request->getUri());
        static::assertSame('multipart/form-data; boundary=request-id-2', $request->getHeaderLine('Content-Type'));
        static::assertSame(
            "--request-id-2\r\n"
            . "Content-Disposition: form-data; name=\"name\"\r\n\r\n"
            . "DPID_202505_24001_000_20250428150823.xml.gz\r\n"
            . "--request-id-2\r\n"
            . "Content-Disposition: form-data; name=\"data\"; filename=\"DPID_202505_24001_000_20250428150823.xml.gz\"\r\n"
            . "Content-Type: application/octet-stream\r\n\r\n"
            . "file-bytes\r\n"
            . "--request-id-2--\r\n",
            (string) $request->getBody(),
        );
    }

    public function testADeclarationCarriesItsTypeInTheQuery(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, WireFixtures::load('upload_response'));

        $harness->gateway->uploadDeclaration(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            DeclarationType::Regularisation,
            InMemoryFileSource::of(FileName::fromString('DPRE_1.xml.gz'), 'x'),
        );

        $request = $harness->stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame(
            GatewayHarness::BASE . '/platforms/platform-1/declarations/upload?type=REGULARISATION',
            (string) $request->getUri(),
        );
    }

    public function testThePlatformIdentifierIsPercentEncodedInThePath(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, WireFixtures::load('upload_response'));

        $harness->gateway->uploadIdentification(
            HttpStacks::tenant(),
            PlatformId::fromString('plat form?#é'),
            InMemoryFileSource::of(FileName::fromString('a.xml.gz'), 'x'),
        );

        static::assertSame(
            GatewayHarness::BASE . '/platforms/plat%20form%3F%23%C3%A9/identifications/upload',
            (string) $harness->stack->lastRequest()->getUri(),
        );
    }

    public function testAnUploadRefusedWithA400IsARejection(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(400, '[{"code":"ERR_X","message":"m"}]');

        try {
            $harness->gateway->uploadDeclaration(
                HttpStacks::tenant(),
                GatewayHarness::platform(),
                DeclarationType::Declaration,
                InMemoryFileSource::of(FileName::fromString('a.xml.gz'), 'x'),
            );
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(['ERR_X'], $exception->errors()->codes());
        }
    }

    public function testAServerErrorOnAnUploadLeavesTheOutcomeUnknown(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(502, '');

        $this->expectException(OutcomeUnknown::class);

        $harness->gateway->uploadIdentification(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            InMemoryFileSource::of(FileName::fromString('a.xml.gz'), 'x'),
        );
    }

    /**
     * @return iterable<string, array{string, Operation}>
     */
    public static function unreadableReferences(): iterable
    {
        foreach ([Operation::UploadIdentification, Operation::UploadDeclaration] as $operation) {
            yield $operation->value . ' empty object' => ['{}', $operation];
            yield $operation->value . ' empty reference' => ['{"urssafRef":""}', $operation];
            yield $operation->value . ' null reference' => ['{"urssafRef":null}', $operation];
            yield $operation->value . ' numeric reference' => ['{"urssafRef":42}', $operation];
            yield $operation->value . ' empty list' => ['[]', $operation];
        }
    }

    #[DataProvider('unreadableReferences')]
    public function testAnAnswerWithoutReadableReferenceLeavesTheOutcomeUnknown(string $body, Operation $operation): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, $body);
        $file = InMemoryFileSource::of(FileName::fromString('a.xml.gz'), 'x');

        try {
            $operation === Operation::UploadIdentification
                ? $harness->gateway->uploadIdentification(HttpStacks::tenant(), GatewayHarness::platform(), $file)
                : $harness->gateway->uploadDeclaration(HttpStacks::tenant(), GatewayHarness::platform(), DeclarationType::Declaration, $file);
            static::fail('Expected an unknown outcome.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame($operation, $exception->operation());
            static::assertTrue($exception->mayHaveReachedUrssaf());
        }
    }

    public function testAnUploadReplayedAfterA401ReopensTheFile(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->stack->respondToken();
        $harness->stack->respond(401, '');
        $harness->stack->respondToken();
        $harness->stack->respond(200, WireFixtures::load('upload_response'));

        $harness->gateway->uploadIdentification(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            InMemoryFileSource::of(FileName::fromString('a.xml.gz'), 'replayed-bytes'),
        );

        $requests = $harness->stack->requests();
        static::assertCount(4, $requests);
        static::assertStringContainsString("\r\n\r\nreplayed-bytes\r\n", (string) $requests[3]->getBody());
    }
}
