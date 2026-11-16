<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\BinaryStream;

/**
 * Application {@see BinaryStream} over the PSR-7 body of a download: read in chunks, never buffered nor decoded
 * (ADR 0005).
 *
 * @internal
 */
final readonly class PsrBinaryStream implements BinaryStream
{
    private const CHUNK_SIZE = 65_536;

    public function __construct(private StreamInterface $body, private StreamFactoryInterface $streamFactory) {}

    public function read(int $length): string
    {
        return $this->body->read($length);
    }

    public function eof(): bool
    {
        return $this->body->eof();
    }

    public function copyTo(mixed $resource): int
    {
        $target = $this->streamFactory->createStreamFromResource($resource);
        $copied = 0;

        try {
            while (!$this->body->eof()) {
                $copied += $target->write($this->body->read(self::CHUNK_SIZE));
            }
        } finally {
            $target->detach();
        }

        return $copied;
    }

    public function close(): void
    {
        $this->body->close();
    }
}
