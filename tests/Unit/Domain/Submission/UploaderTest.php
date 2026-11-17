<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Uploader;

final class UploaderTest extends TestCase
{
    public function testGettersAndMaskedDebugInfo(): void
    {
        $uploader = Uploader::of('user-1', 'jane.doe@example.com');

        static::assertSame('user-1', $uploader->userId());
        static::assertSame('jane.doe@example.com', $uploader->userEmail());
        static::assertSame(['userId' => '***', 'userEmail' => '***'], $uploader->__debugInfo());
        static::assertStringNotContainsString('jane.doe', print_r($uploader, true));
    }

    public function testAbsentValuesStayNull(): void
    {
        $uploader = Uploader::of(null, null);

        static::assertNull($uploader->userId());
        static::assertNull($uploader->userEmail());
        static::assertSame(['userId' => null, 'userEmail' => null], $uploader->__debugInfo());
    }

    public function testEachValueIsMaskedIndependently(): void
    {
        static::assertSame(['userId' => '***', 'userEmail' => null], Uploader::of('user-1', null)->__debugInfo());
        static::assertSame(['userId' => null, 'userEmail' => '***'], Uploader::of(null, 'a@example.com')->__debugInfo());
    }
}
