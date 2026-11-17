<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Typed, defensive view over a decoded JSON document. This is the only place where untyped wire data is read:
 * every accessor either returns a well-typed value or throws {@see UnexpectedUrssafResponse}.
 *
 * One accessor per JSON shape read from URSSAF, hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class Payload
{
    /**
     * @param array<array-key, array<array-key, mixed>|scalar|null> $data
     */
    private function __construct(private array $data) {}

    /**
     * @throws UnexpectedUrssafResponse when the decoded JSON is neither an object nor an array
     */
    public static function fromDecoded(mixed $decoded): self
    {
        if (!\is_array($decoded)) {
            throw UnexpectedUrssafResponse::invalidPayload('body is neither a JSON object nor a JSON array');
        }

        return self::wrap($decoded);
    }

    /**
     * @param array<array-key, mixed> $data decoded JSON object or array
     */
    private static function wrap(array $data): self
    {
        /** @var array<array-key, array<array-key, mixed>|scalar|null> $typed */
        $typed = $data;

        return new self($typed);
    }

    public function isList(): bool
    {
        return array_is_list($this->data);
    }

    /**
     * String value of a key. Null when absent, null or of another type (lenient, for error bodies).
     */
    public function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * String value of a key. Null when absent or null.
     *
     * @throws UnexpectedUrssafResponse when present but not a string
     */
    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_string($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a string', $key));
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not a non-empty string
     */
    public function requireString(string $key): string
    {
        $value = $this->string($key) ?? '';

        if ($value === '') {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or empty', $key));
        }

        return $value;
    }

    /**
     * JSON number of a key, null when absent. An integer beyond the PHP range comes as a string
     * (JSON_BIGINT_AS_STRING): {@see NumberDecoding::decimal()} decides whether the string is acceptable.
     *
     * @throws UnexpectedUrssafResponse when present but neither a number nor a string
     */
    public function number(string $key): int|float|string|null
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_int($value) || \is_float($value) || \is_string($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a number', $key));
    }

    /**
     * Nested JSON object. Null when absent.
     *
     * @throws UnexpectedUrssafResponse when present but not an object
     */
    public function object(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not an object', $key));
        }

        return self::wrap($value);
    }

    /**
     * Nested list of strings. Empty when absent.
     *
     * @return list<string>
     *
     * @throws UnexpectedUrssafResponse when present but not a list of strings
     */
    public function strings(string $key): array
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return [];
        }

        if (!\is_array($value) || !array_is_list($value)) {
            throw self::notStrings($key);
        }

        return array_map(
            static fn(mixed $item): string => \is_string($item) ? $item : throw self::notStrings($key),
            $value,
        );
    }

    private static function notStrings(string $key): UnexpectedUrssafResponse
    {
        return UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a list of strings', $key));
    }

    /**
     * Elements of a top-level JSON array, each of which must be an object.
     *
     * @return list<self>
     *
     * @throws UnexpectedUrssafResponse when this payload is not a list of objects
     */
    public function entries(): array
    {
        if (!array_is_list($this->data)) {
            throw UnexpectedUrssafResponse::invalidPayload('expected a JSON array');
        }

        return array_map(
            static fn(array|bool|float|int|string|null $entry): self => \is_array($entry) && ($entry === [] || !array_is_list($entry))
                ? self::wrap($entry)
                : throw UnexpectedUrssafResponse::invalidPayload('expected a JSON array of objects'),
            $this->data,
        );
    }
}
