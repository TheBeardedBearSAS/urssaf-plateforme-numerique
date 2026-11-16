<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake;

use LogicException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;

/**
 * File source whose stream cannot be read (write-only), keeping the resource it handed out.
 */
final class UnreadableFileSource implements FileSource
{
    /** @var resource|null */
    private mixed $opened = null;

    public function name(): FileName
    {
        return FileName::fromString('DPID_1.xml.gz');
    }

    public function open(): mixed
    {
        $resource = fopen('php://output', 'wb');
        if ($resource === false) {
            throw new LogicException('php://output is not available.');
        }
        $this->opened = $resource;

        return $resource;
    }

    public function isStillOpen(): bool
    {
        return \is_resource($this->opened);
    }
}
