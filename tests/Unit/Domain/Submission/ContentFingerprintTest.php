<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ContentFingerprint;

final class ContentFingerprintTest extends TestCase
{
    private const SHA256_OF_EMPTY = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    public function testFromHex(): void
    {
        $fingerprint = ContentFingerprint::fromHex(self::SHA256_OF_EMPTY);

        static::assertSame(self::SHA256_OF_EMPTY, $fingerprint->value());
        static::assertSame(self::SHA256_OF_EMPTY, (string) $fingerprint);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => [substr(self::SHA256_OF_EMPTY, 1)];
        yield 'too long' => [self::SHA256_OF_EMPTY . '0'];
        yield 'uppercase' => [strtoupper(self::SHA256_OF_EMPTY)];
        yield 'non hexadecimal' => [str_repeat('g', 64)];
        yield 'prefix' => [' ' . self::SHA256_OF_EMPTY];
        yield 'trailing newline' => [self::SHA256_OF_EMPTY . "\n"];
    }

    #[DataProvider('provideInvalidValues')]
    public function testFromHexRejectsInvalidInput(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('content_fingerprint', '64 lowercase hexadecimal characters'));

        ContentFingerprint::fromHex($value);
    }

    public function testEquals(): void
    {
        $fingerprint = ContentFingerprint::fromHex(self::SHA256_OF_EMPTY);

        static::assertTrue($fingerprint->equals(ContentFingerprint::fromHex(self::SHA256_OF_EMPTY)));
        static::assertFalse($fingerprint->equals(ContentFingerprint::fromHex(str_repeat('0', 64))));
    }
}
