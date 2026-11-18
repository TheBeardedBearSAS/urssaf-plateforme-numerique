<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared;

use Stringable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * A JSON number of the API (counter or amount, no unit) kept as its canonical decimal string: no float is stored,
 * no arithmetic is offered (ADR 0006).
 */
final readonly class DecimalNumber implements Stringable
{
    private const SUBJECT = 'decimal_number';
    private const PATTERN = '/^-?(0|[1-9]\d*)(\.\d+)?$/D';
    private const MAX_MAGNITUDE = 1.0e15;
    private const MIN_MAGNITUDE = 1.0e-4;
    private const MAX_DECIMALS = 20;
    private const INTEGRAL_PATTERN = '/^(-?\d+)(?:\.0+)?$/D';

    private function __construct(private string $value) {}

    public static function fromInt(int $value): self
    {
        return new self((string) $value);
    }

    /**
     * Canonical form: shortest fixed-notation round-trip representation, independent of the serialize_precision
     * ini setting ("20.152", "1.0").
     *
     * @throws InvalidValue "decimal_number.out_of_range" for a non-finite float, one of 1.0E+15 or more, or a
     *                      non-zero one below 1.0E-4 (they would need an exponent)
     */
    public static function fromFloat(float $value): self
    {
        $magnitude = abs($value);
        if (!is_finite($value) || $magnitude >= self::MAX_MAGNITUDE || ($magnitude !== 0.0 && $magnitude < self::MIN_MAGNITUDE)) {
            throw self::outOfRange();
        }
        // Bounded search (never an open-ended loop, so every code mutant terminates): a finite float within
        // [MIN_MAGNITUDE, MAX_MAGNITUDE) round-trips with 1 to MAX_DECIMALS decimals (17 significant digits, the first
        // one at the 4th decimal at worst). At least one decimal is written, which gives the ".0" of an integral value.
        $representation = '';
        foreach (range(1, self::MAX_DECIMALS) as $decimals) {
            $representation = \sprintf('%.' . $decimals . 'F', $value);
            if (filter_var($representation, FILTER_VALIDATE_FLOAT) === $value) {
                break;
            }
        }

        return new self($representation);
    }

    /**
     * @throws InvalidValue "decimal_number.invalid_format"
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'decimal number without exponent');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    /**
     * The integer value when the number has no fractional part (or only zeros) and fits in an int, otherwise null.
     */
    public function toInt(): ?int
    {
        $matches = [];
        if (preg_match(self::INTEGRAL_PATTERN, $this->value, $matches) !== 1) {
            return null;
        }
        $integer = filter_var($matches[1], FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function outOfRange(): InvalidValue
    {
        return InvalidValue::because(
            self::SUBJECT . '.out_of_range',
            'Invalid decimal_number: the float is not finite or cannot be written without an exponent.',
        );
    }
}
