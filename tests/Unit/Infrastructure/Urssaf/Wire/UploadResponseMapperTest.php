<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\UploadResponseMapper;

final class UploadResponseMapperTest extends TestCase
{
    public function testItReadsTheOfficialUploadResponse(): void
    {
        $reference = UploadResponseMapper::urssafReference(WireFixtures::fixture('upload_response'));

        static::assertSame('cbcf2700-110d-4abf-9835-a9b46e68d7c4', $reference->value());
    }

    public function testItKeepsAReferenceThatIsNotAUuid(): void
    {
        $reference = UploadResponseMapper::urssafReference(WireFixtures::payload(['urssafRef' => 'unique-file-submission-id-12345']));

        static::assertSame('unique-file-submission-id-12345', $reference->value());
    }

    public function testItRejectsAMissingEmptyOrNonStringReference(): void
    {
        foreach ([[], ['urssafRef' => ''], ['urssafRef' => 12], ['urssafRef' => null]] as $body) {
            try {
                UploadResponseMapper::urssafReference(WireFixtures::payload($body));
                static::fail('Expected an UnexpectedUrssafResponse.');
            } catch (UnexpectedUrssafResponse $exception) {
                static::assertStringContainsString('field "urssafRef" is missing or empty', $exception->getMessage());
            }
        }
    }

    public function testItRejectsAReferenceThatCannotBeUsedInAPath(): void
    {
        try {
            UploadResponseMapper::urssafReference(WireFixtures::payload(['urssafRef' => 'a/b']));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertStringContainsString('field "urssafRef" is not a valid reference', $exception->getMessage());
            static::assertNotNull($exception->getPrevious());
        }
    }
}
