<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf;

/**
 * A downloaded file read as a stream, never buffered nor decoded by the package (ADR 0005).
 */
interface BinaryStream
{
    /**
     * @param positive-int $length maximum number of bytes to read
     *
     * @return string the bytes read; empty at the end of the stream
     */
    public function read(int $length): string;

    public function eof(): bool;

    /**
     * Copies the remaining content to a writable stream resource.
     *
     * @param resource $resource
     *
     * @return int the number of bytes copied
     */
    public function copyTo(mixed $resource): int;

    public function close(): void;
}
