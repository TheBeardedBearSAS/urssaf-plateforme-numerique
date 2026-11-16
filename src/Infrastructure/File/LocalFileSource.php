<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;

/**
 * File of the local file system, streamed from disk at every {@see self::open()} (ADR 0005). The name sent to URSSAF
 * is the base name of the path ({@see self::fromPath()}) unless one is given ({@see self::fromPathNamed()}).
 */
final readonly class LocalFileSource implements FileSource
{
    private function __construct(private string $path, private FileName $name) {}

    /**
     * The name is the base name of the path.
     *
     * @throws InvalidValue "file_source.unreadable" when the path cannot be opened for reading, or a "file_name.*"
     *                      violation when the base name is not a valid file name
     */
    public static function fromPath(string $path): self
    {
        return self::fromPathNamed($path, FileName::fromString(basename($path)));
    }

    /**
     * @throws InvalidValue "file_source.unreadable" when the path cannot be opened for reading
     */
    public static function fromPathNamed(string $path, FileName $name): self
    {
        fclose(self::stream($path));

        return new self($path, $name);
    }

    public function name(): FileName
    {
        return $this->name;
    }

    /**
     * @throws InvalidValue "file_source.unreadable" when the file disappeared or became unreadable
     */
    public function open(): mixed
    {
        return self::stream($this->path);
    }

    /**
     * @return resource
     *
     * @throws InvalidValue "file_source.unreadable"
     */
    private static function stream(string $path): mixed
    {
        $stream = is_file($path) && is_readable($path) ? fopen($path, 'rb') : false;

        return $stream === false
            ? throw InvalidValue::because('file_source.unreadable', \sprintf('The file "%s" cannot be opened for reading.', $path))
            : $stream;
    }
}
