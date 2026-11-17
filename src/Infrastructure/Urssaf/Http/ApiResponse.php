<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http;

use LogicException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\BinaryStream;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\Payload;

/**
 * Successful answer of an API call: a decoded JSON payload, or a binary stream for a download, with its headers and the
 * request id sent with the call (for correlation in logs).
 *
 * @internal
 */
final readonly class ApiResponse
{
    /**
     * @param array<string, string> $headers header lines, indexed by lower-case name
     */
    private function __construct(
        private ?Payload $payload,
        private ?BinaryStream $stream,
        private array $headers,
        private string $requestId,
    ) {}

    /**
     * @param array<string, string> $headers header lines, indexed by lower-case name
     */
    public static function json(Payload $payload, array $headers, string $requestId): self
    {
        return new self($payload, null, $headers, $requestId);
    }

    /**
     * @param array<string, string> $headers header lines, indexed by lower-case name
     */
    public static function binary(BinaryStream $stream, array $headers, string $requestId): self
    {
        return new self(null, $stream, $headers, $requestId);
    }

    /**
     * @throws LogicException when the answer is a binary stream
     */
    public function payload(): Payload
    {
        return $this->payload ?? throw new LogicException('The response is a binary stream, not a JSON payload.');
    }

    /**
     * @throws LogicException when the answer is a JSON payload
     */
    public function stream(): BinaryStream
    {
        return $this->stream ?? throw new LogicException('The response is a JSON payload, not a binary stream.');
    }

    public function isStream(): bool
    {
        return $this->stream instanceof BinaryStream;
    }

    /**
     * Header line, case-insensitive name; null when absent.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }
}
