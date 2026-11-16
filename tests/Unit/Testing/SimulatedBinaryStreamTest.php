<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedBinaryStream;

final class SimulatedBinaryStreamTest extends TestCase
{
    public function testItIsReadInChunksUntilTheEnd(): void
    {
        $stream = new SimulatedBinaryStream('abcde');

        static::assertFalse($stream->eof());
        static::assertSame('ab', $stream->read(2));
        static::assertSame('cd', $stream->read(2));
        static::assertFalse($stream->eof());
        static::assertSame('e', $stream->read(2));
        static::assertTrue($stream->eof());
        static::assertSame('', $stream->read(2));
    }

    public function testAnEmptyContentIsAtItsEndAtOnce(): void
    {
        static::assertTrue((new SimulatedBinaryStream(''))->eof());
    }

    public function testTheRestIsCopiedToAResource(): void
    {
        $stream = new SimulatedBinaryStream('abcde');
        $stream->read(2);
        $target = fopen('php://memory', 'w+b');
        static::assertIsResource($target);

        $copied = $stream->copyTo($target);

        rewind($target);
        static::assertSame(3, $copied);
        static::assertSame('cde', stream_get_contents($target));
        static::assertTrue($stream->eof());
        static::assertSame(0, $stream->copyTo($target));
        fclose($target);
    }

    public function testClosingReleasesTheContent(): void
    {
        $stream = new SimulatedBinaryStream('abcde');

        $stream->close();

        static::assertTrue($stream->eof());
        static::assertSame('', $stream->read(5));
    }
}
