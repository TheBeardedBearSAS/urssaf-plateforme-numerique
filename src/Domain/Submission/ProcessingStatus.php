<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

/**
 * Processing status of a file as reported by the API: the raw code and label plus the known status, if any
 * (an unexpected code never breaks a read).
 */
final readonly class ProcessingStatus
{
    private function __construct(
        private ?string $raw,
        private ?string $label,
        private ?FileStatus $status,
    ) {}

    public static function of(?string $raw, ?string $label): self
    {
        return new self($raw, $label, FileStatus::fromCode($raw));
    }

    public function raw(): ?string
    {
        return $this->raw;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function status(): ?FileStatus
    {
        return $this->status;
    }
}
