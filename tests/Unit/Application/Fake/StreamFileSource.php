<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;

/**
 * File source over a string that keeps every stream it opened, to check that they were all closed.
 */
final class StreamFileSource implements FileSource
{
    /** @var list<resource> */
    private array $opened = [];

    public function __construct(private readonly FileName $name, private readonly string $contents) {}

    public static function named(string $name, string $contents): self
    {
        return new self(FileName::fromString($name), $contents);
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
        $this->opened[] = $stream;

        return $stream;
    }

    public function openedCount(): int
    {
        return \count($this->opened);
    }

    public function allClosed(): bool
    {
        foreach ($this->opened as $stream) {
            if (\is_resource($stream)) {
                return false;
            }
        }

        return true;
    }
}
