<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\File;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\LocalFileSource;

final class LocalFileSourceTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../../Fixtures/wire/upload_response.json';

    /** @var list<string> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            if (!is_file($path)) {
                continue;
            }

            unlink($path);
        }
    }

    public function testTheNameDefaultsToTheBaseNameOfThePath(): void
    {
        $source = LocalFileSource::fromPath(self::FIXTURE);

        static::assertSame('upload_response.json', $source->name()->value());
    }

    public function testAGivenNameWins(): void
    {
        $source = LocalFileSource::fromPathNamed(self::FIXTURE, FileName::fromString('DPID_1.xml.gz'));

        static::assertSame('DPID_1.xml.gz', $source->name()->value());
    }

    public function testEveryOpenReturnsAFreshStreamAtTheStart(): void
    {
        $source = LocalFileSource::fromPath(self::FIXTURE);
        $expected = file_get_contents(self::FIXTURE);

        $first = $source->open();
        static::assertSame($expected, stream_get_contents($first));
        fclose($first);

        $second = $source->open();
        static::assertSame(0, ftell($second));
        static::assertSame($expected, stream_get_contents($second));
        fclose($second);
    }

    public function testAMissingPathIsRefusedWithoutAPhpWarning(): void
    {
        try {
            LocalFileSource::fromPath(__DIR__ . '/does-not-exist.xml.gz');
            static::fail('Expected an unreadable file.');
        } catch (InvalidValue $exception) {
            static::assertSame('file_source.unreadable', $exception->violationCode());
        }
    }

    public function testAPathWithANullByteIsRefused(): void
    {
        try {
            LocalFileSource::fromPathNamed("/data/file\0.xml.gz", FileName::fromString('a.xml.gz'));
            static::fail('Expected an unreadable file.');
        } catch (InvalidValue $exception) {
            static::assertSame('file_source.unreadable', $exception->violationCode());
        }
    }

    public function testADirectoryIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        LocalFileSource::fromPath(__DIR__);
    }

    public function testAnInvalidBaseNameIsRefused(): void
    {
        $path = $this->scratch("bad\"name.xml");

        try {
            LocalFileSource::fromPath($path);
            static::fail('Expected an invalid file name.');
        } catch (InvalidValue $exception) {
            static::assertSame('file_name.invalid_character', $exception->violationCode());
        }
    }

    public function testAFileRemovedAfterwardsIsReportedAtOpen(): void
    {
        $path = $this->scratch('removed.xml.gz');
        $source = LocalFileSource::fromPath($path);
        unlink($path);

        try {
            $source->open();
            static::fail('Expected an unreadable file.');
        } catch (InvalidValue $exception) {
            static::assertSame('file_source.unreadable', $exception->violationCode());
        }
    }

    private function scratch(string $name): string
    {
        $directory = __DIR__ . '/../../../../var/test-files';
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new LogicException('Cannot create the scratch directory.');
        }
        $path = $directory . '/' . $name;
        file_put_contents($path, 'content');
        $this->created[] = $path;

        return $path;
    }
}
