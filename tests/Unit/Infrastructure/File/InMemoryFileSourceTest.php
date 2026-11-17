<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\File;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\InMemoryFileSource;

final class InMemoryFileSourceTest extends TestCase
{
    public function testItExposesItsName(): void
    {
        $source = InMemoryFileSource::of(FileName::fromString('DPDE_1.xml.gz'), 'abc');

        static::assertSame('DPDE_1.xml.gz', $source->name()->value());
    }

    public function testEveryOpenReturnsAFreshStreamAtTheStart(): void
    {
        $source = InMemoryFileSource::of(FileName::fromString('a.xml.gz'), "bytes\x00\xff");

        $first = $source->open();
        static::assertSame("bytes\x00\xff", stream_get_contents($first));
        fclose($first);

        $second = $source->open();
        static::assertSame(0, ftell($second));
        static::assertSame("bytes\x00\xff", stream_get_contents($second));
        fclose($second);
    }

    public function testAnEmptyContentIsAnEmptyStream(): void
    {
        $stream = InMemoryFileSource::of(FileName::fromString('a.xml.gz'), '')->open();

        static::assertSame('', stream_get_contents($stream));
        fclose($stream);
    }
}
