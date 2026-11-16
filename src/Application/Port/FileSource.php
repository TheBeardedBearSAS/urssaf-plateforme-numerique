<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;

/**
 * A file to upload, read as a stream and never loaded in memory by the package (ADR 0005).
 */
interface FileSource
{
    public function name(): FileName;

    /**
     * Opens the content. Every call returns a fresh readable stream positioned at the start (an upload may be replayed
     * after a 401); the caller closes it.
     *
     * @return resource
     */
    public function open(): mixed;
}
