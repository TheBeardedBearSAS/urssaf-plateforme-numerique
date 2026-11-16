<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;

/**
 * Description of one API call, independent from the HTTP library (ADR 0002). The query is an ordered list of
 * name / value pairs: a name may repeat (`sort`).
 *
 * @internal
 */
final readonly class ApiRequest
{
    /**
     * @param list<array{0: string, 1: string}> $query
     */
    private function __construct(
        private Operation $operation,
        private string $method,
        private string $path,
        private array $query,
        private ?FileSource $file,
        private bool $download,
    ) {}

    /**
     * @param list<array{0: string, 1: string}> $query
     */
    public static function get(Operation $operation, string $path, array $query): self
    {
        return new self($operation, 'GET', $path, $query, null, false);
    }

    /**
     * @param list<array{0: string, 1: string}> $query
     */
    public static function multipart(Operation $operation, string $path, array $query, FileSource $file): self
    {
        return new self($operation, 'POST', $path, $query, $file, false);
    }

    public static function download(Operation $operation, string $path): self
    {
        return new self($operation, 'GET', $path, [], null, true);
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * The file sent as a `multipart/form-data` body, null for the other calls.
     */
    public function file(): ?FileSource
    {
        return $this->file;
    }

    /**
     * True when the answer is a binary file, streamed and never decoded (ADR 0005).
     */
    public function isDownload(): bool
    {
        return $this->download;
    }
}
