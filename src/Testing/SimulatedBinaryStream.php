<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Testing;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\BinaryStream;

/**
 * In-memory {@see BinaryStream} over the content of a report file scripted in the {@see SimulatedPlatformGateway}.
 *
 * @internal
 */
final class SimulatedBinaryStream implements BinaryStream
{
    private int $offset = 0;

    public function __construct(private string $content) {}

    public function read(int $length): string
    {
        $chunk = substr($this->content, $this->offset, $length);
        $this->offset += \strlen($chunk);

        return $chunk;
    }

    public function eof(): bool
    {
        return $this->offset >= \strlen($this->content);
    }

    public function copyTo(mixed $resource): int
    {
        $rest = substr($this->content, $this->offset);
        $this->offset += \strlen($rest);
        fwrite($resource, $rest);

        return \strlen($rest);
    }

    public function close(): void
    {
        $this->content = '';
    }
}
