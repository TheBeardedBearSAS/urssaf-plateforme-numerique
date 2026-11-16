<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\BinaryStream;

/**
 * A downloaded stream that counts how many times it was closed and can fail while being copied.
 */
final class RecordingStream implements BinaryStream
{
    private int $closed = 0;

    public function __construct(private readonly bool $failing) {}

    public function closed(): int
    {
        return $this->closed;
    }

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
        if ($this->failing) {
            throw new RuntimeException('The connection was lost.');
        }

        return (int) fwrite($resource, 'report content');
    }

    public function close(): void
    {
        ++$this->closed;
    }
}
