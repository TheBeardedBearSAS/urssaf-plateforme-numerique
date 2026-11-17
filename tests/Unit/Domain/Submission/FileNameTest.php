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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;

final class FileNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideForbiddenFileNames(): iterable
    {
        yield 'nul' => ["a\x00b.csv"];
        yield 'unit separator' => ["a\x1Fb.csv"];
        yield 'newline' => ["a\nb.csv"];
        yield 'tab' => ["a\tb.csv"];
        yield 'delete' => ["a\x7Fb.csv"];
        yield 'double quote' => ['a"b.csv'];
    }

    #[DataProvider('provideForbiddenFileNames')]
    public function testRejectsHeaderUnsafeCharacters(string $value): void
    {
        try {
            FileName::fromString($value);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame('file_name.invalid_character', $exception->violationCode());
            static::assertSame('Invalid file_name: control characters and double quotes are not allowed.', $exception->getMessage());
        }
    }

    public function testAcceptsSpaceAndTheCharactersAroundTheForbiddenRange(): void
    {
        static::assertSame(" é'#~.csv", FileName::fromString(" é'#~.csv")->value());
        static::assertSame('a b.csv', FileName::fromString('a b.csv')->value());
    }
}
