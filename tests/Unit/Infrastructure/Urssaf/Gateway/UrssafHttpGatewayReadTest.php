<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Gateway;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http\HttpStacks;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

final class UrssafHttpGatewayReadTest extends TestCase
{
    public function testAnIdentificationIsReadAndMapped(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, WireFixtures::load('identification_response_handbuilt'));

        $identification = $harness->gateway->getIdentification(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            GatewayHarness::reference(),
        );

        static::assertSame('cd48ba8a-3a7e-4b80-8e87-f14a5542c6cf', $identification->identificationFileRef());
        // 20:04:14 in Paris (UTC+2): the configured API time zone is applied.
        static::assertSame('2025-04-28T18:04:14Z', $identification->dates()->lastUpdateDate()?->format());
        $request = $harness->stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            GatewayHarness::BASE . '/platforms/platform-1/identifications/unique-file-submission-id-12345',
            (string) $request->getUri(),
        );
        static::assertSame('application/json', $request->getHeaderLine('Accept'));
        static::assertFalse($request->hasHeader('Content-Type'));
    }

    public function testADeclarationIsReadAndMapped(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, WireFixtures::load('declaration_response_handbuilt'));

        $declaration = $harness->gateway->getDeclaration(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            GatewayHarness::reference(),
        );

        static::assertSame('DPRE_202505_24001_000_20250428150823.xml.gz', $declaration->declarationFileName());
        static::assertSame(
            GatewayHarness::BASE . '/platforms/platform-1/declarations/unique-file-submission-id-12345',
            (string) $harness->stack->lastRequest()->getUri(),
        );
    }

    public function testTheReferenceIsPercentEncodedInThePath(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, '{}');

        $harness->gateway->getDeclaration(
            HttpStacks::tenant(),
            PlatformId::fromString('p'),
            UrssafReference::fromString('ref with space?x=1'),
        );

        static::assertSame(
            GatewayHarness::BASE . '/platforms/p/declarations/ref%20with%20space%3Fx%3D1',
            (string) $harness->stack->lastRequest()->getUri(),
        );
    }

    public function testAnUnknownIdentificationIsNotFound(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(404, '[{"code":"ERR_NOT_FOUND","message":"m"}]');

        $this->expectException(UrssafResourceNotFound::class);

        $harness->gateway->getIdentification(HttpStacks::tenant(), GatewayHarness::platform(), GatewayHarness::reference());
    }

    public function testAForbiddenCallIsAnAuthenticationFailure(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(403, '');

        $this->expectException(UrssafAuthenticationFailed::class);

        $harness->gateway->getDeclaration(HttpStacks::tenant(), GatewayHarness::platform(), GatewayHarness::reference());
    }
}
