<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;

final class DecimalNumberParsingTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidStrings(): iterable
    {
        yield 'zero' => ['0'];
        yield 'integer' => ['12345'];
        yield 'negative' => ['-12'];
        yield 'decimal' => ['20.152'];
        yield 'zero decimal' => ['0.5'];
        yield 'trailing zeros kept' => ['1.50'];
        yield 'negative zero' => ['-0'];
    }

    #[DataProvider('provideValidStrings')]
    public function testFromString(string $value): void
    {
        static::assertSame($value, DecimalNumber::fromString($value)->toString());
        static::assertSame($value, (string) DecimalNumber::fromString($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidStrings(): iterable
    {
        yield 'empty' => [''];
        yield 'leading zero' => ['01'];
        yield 'plus sign' => ['+1'];
        yield 'exponent' => ['1e5'];
        yield 'no fraction digits' => ['1.'];
        yield 'no integer digits' => ['.5'];
        yield 'comma' => ['1,5'];
        yield 'letters' => ['abc'];
        yield 'prefix' => [' 1'];
        yield 'trailing newline' => ["1\n"];
        yield 'two dots' => ['1.2.3'];
    }

    #[DataProvider('provideInvalidStrings')]
    public function testFromStringRejectsInvalidInput(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('decimal_number', 'decimal number without exponent'));

        DecimalNumber::fromString($value);
    }

    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function provideIntegerViews(): iterable
    {
        yield 'integer' => ['42', 42];
        yield 'negative integer' => ['-42', -42];
        yield 'zero' => ['0', 0];
        yield 'negative zero' => ['-0', 0];
        yield 'zero fraction' => ['5.0', 5];
        yield 'several zeros' => ['5.000', 5];
        yield 'negative zero fraction' => ['-5.00', -5];
        yield 'fraction' => ['5.5', null];
        yield 'trailing non zero' => ['5.10', null];
        yield 'leading non zero' => ['5.01', null];
        yield 'small fraction' => ['0.001', null];
        yield 'max int' => [(string) PHP_INT_MAX, PHP_INT_MAX];
        yield 'min int' => [(string) PHP_INT_MIN, PHP_INT_MIN];
        yield 'above max int' => ['9223372036854775808', null];
        yield 'below min int' => ['-9223372036854775809', null];
        yield 'above max int with zero fraction' => ['9223372036854775808.0', null];
    }

    #[DataProvider('provideIntegerViews')]
    public function testToInt(string $value, ?int $expected): void
    {
        static::assertSame($expected, DecimalNumber::fromString($value)->toInt());
    }
}
