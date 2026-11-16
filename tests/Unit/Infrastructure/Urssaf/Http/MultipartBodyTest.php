<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\MultipartBody;

final class MultipartBodyTest extends TestCase
{
    /**
     * @return resource
     */
    private static function resourceOf(string $contents): mixed
    {
        $resource = fopen('php://temp', 'w+b');
        if ($resource === false) {
            throw new LogicException('php://temp is not available.');
        }
        fwrite($resource, $contents);
        rewind($resource);

        return $resource;
    }

    public function testItWritesTheNameAndDataPartsWithTheGivenBoundary(): void
    {
        $content = self::resourceOf("\x1f\x8b\x08\x00binary\r\n--boundary-1 \x00\xff");

        [$body, $contentType] = (new MultipartBody(new Psr17Factory()))->build(
            FileName::fromString('DPID_4300001_2026-05_001.xml.gz'),
            $content,
            'boundary-1',
        );

        static::assertSame('multipart/form-data; boundary=boundary-1', $contentType);
        static::assertSame(
            "--boundary-1\r\n"
            . "Content-Disposition: form-data; name=\"name\"\r\n"
            . "\r\n"
            . "DPID_4300001_2026-05_001.xml.gz\r\n"
            . "--boundary-1\r\n"
            . "Content-Disposition: form-data; name=\"data\"; filename=\"DPID_4300001_2026-05_001.xml.gz\"\r\n"
            . "Content-Type: application/octet-stream\r\n"
            . "\r\n"
            . "\x1f\x8b\x08\x00binary\r\n--boundary-1 \x00\xff\r\n"
            . "--boundary-1--\r\n",
            (string) $body,
        );
    }

    public function testTheBodyIsPositionedAtItsStartAndReadsTwice(): void
    {
        [$body] = (new MultipartBody(new Psr17Factory()))->build(
            FileName::fromString('a.xml.gz'),
            self::resourceOf('abc'),
            'b',
        );

        static::assertSame(0, $body->tell());
        static::assertSame('--b', $body->read(3));
        static::assertSame(\strlen((string) $body), $body->getSize());
    }

    public function testAnEmptyFileGivesAnEmptyDataPart(): void
    {
        [$body] = (new MultipartBody(new Psr17Factory()))->build(
            FileName::fromString('a.xml.gz'),
            self::resourceOf(''),
            'b',
        );

        static::assertStringEndsWith("\r\n\r\n\r\n--b--\r\n", (string) $body);
    }

    public function testTheContentResourceStaysOpenForTheCaller(): void
    {
        $content = self::resourceOf('abc');

        (new MultipartBody(new Psr17Factory()))->build(FileName::fromString('a.xml.gz'), $content, 'b');

        static::assertTrue(\is_resource($content));
        static::assertTrue(feof($content));
        static::assertTrue(fclose($content));
    }

    public function testAFileLargerThanOneChunkIsCopiedWhole(): void
    {
        $contents = random_bytes(200_000);

        [$body] = (new MultipartBody(new Psr17Factory()))->build(
            FileName::fromString('a.xml.gz'),
            self::resourceOf($contents),
            'b',
        );

        $written = (string) $body;
        static::assertStringContainsString($contents, $written);
        static::assertSame(
            \strlen($contents) + \strlen("--b\r\nContent-Disposition: form-data; name=\"name\"\r\n\r\na.xml.gz\r\n--b\r\n"
            . "Content-Disposition: form-data; name=\"data\"; filename=\"a.xml.gz\"\r\nContent-Type: application/octet-stream\r\n\r\n"
            . "\r\n--b--\r\n"),
            \strlen($written),
        );
    }

    public function testAnUnreadableContentFailsAndStaysOpen(): void
    {
        $content = fopen('php://output', 'wb');
        if ($content === false) {
            throw new LogicException('php://output is not available.');
        }
        $failed = false;

        try {
            (new MultipartBody(new Psr17Factory()))->build(FileName::fromString('a.xml.gz'), $content, 'b');
        } catch (RuntimeException) {
            $failed = true;
        }

        static::assertTrue($failed);
        static::assertSame('wb', stream_get_meta_data($content)['mode']);
    }
}
