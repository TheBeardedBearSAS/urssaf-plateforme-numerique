<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\PsrBinaryStream;

final class PsrBinaryStreamTest extends TestCase
{
    /**
     * @return resource
     */
    private static function target(string $mode = 'w+b'): mixed
    {
        $resource = fopen('php://temp', $mode);
        if ($resource === false) {
            throw new LogicException('php://temp is not available.');
        }

        return $resource;
    }

    private static function stream(string $contents): PsrBinaryStream
    {
        return new PsrBinaryStream(Stream::create($contents), new Psr17Factory());
    }

    public function testItReadsInChunksUntilTheEnd(): void
    {
        $stream = self::stream('abcdef');

        static::assertFalse($stream->eof());
        static::assertSame('abcd', $stream->read(4));
        static::assertFalse($stream->eof());
        static::assertSame('ef', $stream->read(4));
        static::assertSame('', $stream->read(4));
        static::assertTrue($stream->eof());
    }

    public function testItCopiesTheRemainingContentToAResource(): void
    {
        $contents = random_bytes(200_000);
        $stream = self::stream($contents);
        $target = self::target();

        $copied = $stream->copyTo($target);

        static::assertSame(200_000, $copied);
        rewind($target);
        static::assertSame($contents, stream_get_contents($target));
        static::assertTrue(\is_resource($target));
        static::assertTrue($stream->eof());
    }

    public function testItCopiesOnlyWhatWasNotReadYet(): void
    {
        $stream = self::stream('abcdef');
        $stream->read(2);
        $target = self::target();

        static::assertSame(4, $stream->copyTo($target));
        rewind($target);
        static::assertSame('cdef', stream_get_contents($target));
    }

    public function testItCopiesAnEmptyBody(): void
    {
        static::assertSame(0, self::stream('')->copyTo(self::target()));
    }

    public function testACopyToANonWritableResourceFailsAndLeavesItOpen(): void
    {
        $target = fopen(__FILE__, 'rb');
        if ($target === false) {
            throw new LogicException('The test file is not readable.');
        }
        $failed = false;

        try {
            self::stream('abc')->copyTo($target);
        } catch (RuntimeException) {
            $failed = true;
        }

        static::assertTrue($failed);
        static::assertSame('rb', stream_get_meta_data($target)['mode']);
    }

    public function testCloseReleasesTheBody(): void
    {
        $body = Stream::create('abc');
        $stream = new PsrBinaryStream($body, new Psr17Factory());

        $stream->close();

        static::assertFalse($body->isReadable());
    }
}
