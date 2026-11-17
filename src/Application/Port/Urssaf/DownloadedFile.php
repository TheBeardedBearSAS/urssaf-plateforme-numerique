<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf;

/**
 * A file downloaded from URSSAF: its content stream and the raw headers describing it, when present.
 */
final readonly class DownloadedFile
{
    public function __construct(
        private BinaryStream $stream,
        private ?string $contentType,
        private ?string $contentDisposition,
    ) {}

    public function stream(): BinaryStream
    {
        return $this->stream;
    }

    public function contentType(): ?string
    {
        return $this->contentType;
    }

    public function contentDisposition(): ?string
    {
        return $this->contentDisposition;
    }
}
