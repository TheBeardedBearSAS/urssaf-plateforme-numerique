<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;

/**
 * File whose content is held in memory: meant for tests and small generated files (ADR 0005).
 */
final readonly class InMemoryFileSource implements FileSource
{
    private function __construct(private FileName $name, private string $contents) {}

    public static function of(FileName $name, string $contents): self
    {
        return new self($name, $contents);
    }

    public function name(): FileName
    {
        return $this->name;
    }

    public function open(): mixed
    {
        $stream = fopen('php://temp', 'w+b');
        \assert(\is_resource($stream), 'php://temp is always available.');
        fwrite($stream, $this->contents);
        rewind($stream);

        return $stream;
    }
}
