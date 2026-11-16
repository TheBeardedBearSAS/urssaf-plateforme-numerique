<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\BinaryStream;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;

final class DownloadedFileTest extends TestCase
{
    public function testAccessors(): void
    {
        $stream = self::emptyStream();

        $file = new DownloadedFile($stream, 'application/octet-stream', 'attachment; filename="report.csv"');

        static::assertSame($stream, $file->stream());
        static::assertSame('application/octet-stream', $file->contentType());
        static::assertSame('attachment; filename="report.csv"', $file->contentDisposition());
    }

    public function testHeadersAreOptional(): void
    {
        $file = new DownloadedFile(self::emptyStream(), null, null);

        static::assertNull($file->contentType());
        static::assertNull($file->contentDisposition());
    }

    private static function emptyStream(): BinaryStream
    {
        return new class implements BinaryStream {
            public function read(int $length): string
            {
                return '';
            }

            public function eof(): bool
            {
                return true;
            }

            public function copyTo(mixed $resource): int
            {
                return 0;
            }

            public function close(): void {}
        };
    }
}
