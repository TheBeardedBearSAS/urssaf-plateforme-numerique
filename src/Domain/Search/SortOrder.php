<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Search;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * One sort criterion, sent as "field:ASC" or "field:DESC". The sortable fields are not published, so the field is
 * only checked for shape.
 */
final readonly class SortOrder
{
    private const SUBJECT = 'sort_order';
    private const MAX_LENGTH = 64;
    private const FIELD_PATTERN = '/^[A-Za-z0-9_.]+$/D';

    private function __construct(
        private string $field,
        private SortDirection $direction,
    ) {}

    /**
     * @throws InvalidValue "sort_order.invalid_field"
     */
    public static function of(string $field, SortDirection $direction): self
    {
        if (\strlen($field) > self::MAX_LENGTH || preg_match(self::FIELD_PATTERN, $field) !== 1) {
            throw InvalidValue::because(
                self::SUBJECT . '.invalid_field',
                'Invalid sort_order: the field must be 1 to 64 characters among letters, digits, "_" and ".".',
                ['max_length' => self::MAX_LENGTH],
            );
        }

        return new self($field, $direction);
    }

    /**
     * Parses "field:ASC" / "field:DESC".
     *
     * @throws InvalidValue "sort_order.invalid_format" or "sort_order.invalid_field"
     */
    public static function fromWire(string $value): self
    {
        $separator = strrpos($value, ':');
        $direction = $separator === false ? null : SortDirection::tryFrom(substr($value, $separator + 1));
        if ($separator === false || $direction === null) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'field:ASC or field:DESC');
        }

        return self::of(substr($value, 0, $separator), $direction);
    }

    public function field(): string
    {
        return $this->field;
    }

    public function direction(): SortDirection
    {
        return $this->direction;
    }

    /**
     * "field:ASC" / "field:DESC".
     */
    public function toWire(): string
    {
        return $this->field . ':' . $this->direction->value;
    }
}
