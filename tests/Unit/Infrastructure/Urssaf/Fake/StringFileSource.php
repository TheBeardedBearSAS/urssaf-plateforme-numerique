<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake;

use LogicException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;

/**
 * File source over a string, counting how many times it was opened.
 */
final class StringFileSource implements FileSource
{
    private int $opened = 0;

    public function __construct(private readonly FileName $name, private readonly string $contents) {}

    public function name(): FileName
    {
        return $this->name;
    }

    public function open(): mixed
    {
        ++$this->opened;
        $resource = fopen('php://temp', 'w+b');
        if ($resource === false) {
            throw new LogicException('php://temp is not available.');
        }
        fwrite($resource, $this->contents);
        rewind($resource);

        return $resource;
    }

    public function timesOpened(): int
    {
        return $this->opened;
    }
}
