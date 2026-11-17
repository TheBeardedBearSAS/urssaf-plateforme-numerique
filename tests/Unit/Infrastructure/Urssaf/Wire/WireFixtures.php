<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\Payload;

/**
 * Loads the wire fixtures of tests/Fixtures/wire.
 */
final class WireFixtures
{
    private function __construct() {}

    public static function load(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../../../Fixtures/wire/' . $name . '.json');
        if ($content === false) {
            throw new RuntimeException('Missing fixture ' . $name);
        }

        return $content;
    }

    public static function fixture(string $name): Payload
    {
        return JsonDecoder::decode(self::load($name));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function payload(array $data): Payload
    {
        return JsonDecoder::decode(json_encode($data, JSON_THROW_ON_ERROR));
    }
}
