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

use Stringable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * Name of an uploaded file, written in a multipart header: no control character and no double quote.
 * No naming convention is checked (ADR 0005).
 */
final readonly class FileName implements Stringable
{
    private const SUBJECT = 'file_name';
    public const MAX_LENGTH = 255;
    private const FORBIDDEN = '/[\x00-\x1F\x7F"]/';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "file_name.empty", "file_name.too_long" or "file_name.invalid_character"
     */
    public static function fromString(string $value): self
    {
        if ($value === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
        }
        if (preg_match(self::FORBIDDEN, $value) === 1) {
            throw InvalidValue::because(
                self::SUBJECT . '.invalid_character',
                'Invalid file_name: control characters and double quotes are not allowed.',
            );
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
