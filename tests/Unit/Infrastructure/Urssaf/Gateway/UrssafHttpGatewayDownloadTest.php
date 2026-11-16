<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Gateway;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http\HttpStacks;

final class UrssafHttpGatewayDownloadTest extends TestCase
{
    public function testAnIdentificationFileIsStreamedWithItsHeaders(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, "\x1f\x8b binary", [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="global_report.xml"',
        ]);

        $file = $harness->gateway->downloadIdentificationFile(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            GatewayHarness::reference(),
            FileReference::of('a1b2c3d4-e5f6', 'global_report.xml'),
        );

        static::assertSame('application/octet-stream', $file->contentType());
        static::assertSame('attachment; filename="global_report.xml"', $file->contentDisposition());
        static::assertSame("\x1f\x8b binary", $file->stream()->read(100));
        $request = $harness->stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame('application/octet-stream, application/json', $request->getHeaderLine('Accept'));
        static::assertSame(
            GatewayHarness::BASE . '/platforms/platform-1/identifications/unique-file-submission-id-12345/file/a1b2c3d4-e5f6',
            (string) $request->getUri(),
        );
    }

    public function testADeclarationFileWithoutHeadersHasNoTypeNorDisposition(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, 'x');

        $file = $harness->gateway->downloadDeclarationFile(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            GatewayHarness::reference(),
            FileReference::of('file id?', null),
        );

        static::assertNull($file->contentType());
        static::assertNull($file->contentDisposition());
        static::assertSame(
            GatewayHarness::BASE . '/platforms/platform-1/declarations/unique-file-submission-id-12345/file/file%20id%3F',
            (string) $harness->stack->lastRequest()->getUri(),
        );
    }

    public function testAMissingFileIsNotFound(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(404, '');

        $this->expectException(UrssafResourceNotFound::class);

        $harness->gateway->downloadDeclarationFile(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            GatewayHarness::reference(),
            FileReference::of('f', null),
        );
    }
}
