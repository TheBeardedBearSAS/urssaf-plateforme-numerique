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

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;

final class JsonApiClientDownloadTest extends TestCase
{
    public function testASuccessIsStreamedWithoutDecodingItAndKeepsItsHeaders(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $bytes = "\x1f\x8b\x08\x00 not json \xff";
        $stack->respond(200, $bytes, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="DPID_1_report.xml.gz"',
        ]);

        $response = $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::download(Operation::DownloadIdentificationFile, '/p/file/f'),
        );

        static::assertTrue($response->isStream());
        static::assertSame('application/octet-stream', $response->header('Content-Type'));
        static::assertSame('attachment; filename="DPID_1_report.xml.gz"', $response->header('Content-Disposition'));
        $target = fopen('php://temp', 'w+b');
        if ($target === false) {
            throw new LogicException('php://temp is not available.');
        }
        static::assertSame(\strlen($bytes), $response->stream()->copyTo($target));
        rewind($target);
        static::assertSame($bytes, stream_get_contents($target));
    }

    public function testAnEmptyBinaryIsAccepted(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '');

        $response = $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::download(Operation::DownloadDeclarationFile, '/p/file/f'),
        );

        static::assertSame('', $response->stream()->read(10));
        static::assertTrue($response->stream()->eof());
    }

    public function testA404IsNotFoundAndNotRetryable(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(404, '[{"code":"ERR_FILE","message":"m"}]');

        try {
            $stack->jsonClient()->send(
                HttpStacks::tenant(),
                $stack->credentials->forTenant(HttpStacks::tenant()),
                ApiRequest::download(Operation::DownloadDeclarationFile, '/p/file/f'),
            );
            static::fail('Expected a not-found exception.');
        } catch (UrssafResourceNotFound $exception) {
            static::assertSame(Operation::DownloadDeclarationFile, $exception->operation());
            static::assertSame(['ERR_FILE'], $exception->errors()->codes());
        }
    }

    public function testAServerErrorIsRetryable(): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(500, '');

        $this->expectException(UrssafUnavailable::class);

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::download(Operation::DownloadIdentificationFile, '/p/file/f'),
        );
    }
}
