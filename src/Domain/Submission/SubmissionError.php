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
 * Error returned by URSSAF for a refused upload ("code" and "message" of the API, verbatim). The "description" is
 * never stored: it may echo submitted values (ADR 0009).
 */
final readonly class SubmissionError
{
    private function __construct(private ?string $code, private ?string $message) {}

    public static function of(?string $code, ?string $message): self
    {
        return new self($code, $message);
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function message(): ?string
    {
        return $this->message;
    }
}
