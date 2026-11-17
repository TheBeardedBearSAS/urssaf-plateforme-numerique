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

use SensitiveParameter;

/**
 * Who uploaded a file ("userId" and "userEmail" of the API). Personal data (ADR 0009): masked in debug output, only
 * exposed on the live read models and never persisted nor logged.
 */
final readonly class Uploader
{
    private const MASK = '***';

    private function __construct(
        private ?string $userId,
        private ?string $userEmail,
    ) {}

    public static function of(#[SensitiveParameter] ?string $userId, #[SensitiveParameter] ?string $userEmail): self
    {
        return new self($userId, $userEmail);
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function userEmail(): ?string
    {
        return $this->userEmail;
    }

    /**
     * Personal data: masked in debug output (var_dump, print_r, dumpers).
     *
     * @return array{userId: ?string, userEmail: ?string}
     */
    public function __debugInfo(): array
    {
        return [
            'userId' => $this->userId === null ? null : self::MASK,
            'userEmail' => $this->userEmail === null ? null : self::MASK,
        ];
    }
}
