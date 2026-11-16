<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Exception;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\Severity;

final class InvalidValueTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): InvalidValue, string, string, array<string, scalar>}>
     */
    public static function provideNamedConstructors(): iterable
    {
        yield 'because' => [
            static fn(): InvalidValue => InvalidValue::because('foo.bar', 'Custom message.', ['limit' => 3]),
            'foo.bar',
            'Custom message.',
            ['limit' => 3],
        ];
        yield 'because without params' => [static fn(): InvalidValue => InvalidValue::because('foo.bar', 'Custom message.'), 'foo.bar', 'Custom message.', []];
        yield 'empty' => [static fn(): InvalidValue => InvalidValue::empty('tenant_id'), 'tenant_id.empty', 'Invalid tenant_id: the value must not be empty.', []];
        yield 'too long' => [
            static fn(): InvalidValue => InvalidValue::tooLong('tenant_id', 64),
            'tenant_id.too_long',
            'Invalid tenant_id: the value must not exceed 64 characters.',
            ['max_length' => 64],
        ];
        yield 'invalid format' => [
            static fn(): InvalidValue => InvalidValue::invalidFormat('country_code', '5 digits'),
            'country_code.invalid_format',
            'Invalid country_code: the value does not match the expected format (5 digits).',
            ['expected_format' => '5 digits'],
        ];
        yield 'negative' => [static fn(): InvalidValue => InvalidValue::negative('money'), 'money.negative', 'Invalid money: the value must not be negative.', []];
        yield 'not positive' => [
            static fn(): InvalidValue => InvalidValue::notPositive('quantity'),
            'quantity.not_positive',
            'Invalid quantity: the value must be strictly positive.',
            [],
        ];
        yield 'too many decimals' => [
            static fn(): InvalidValue => InvalidValue::tooManyDecimals('money', 2),
            'money.too_many_decimals',
            'Invalid money: the value must not have more than 2 decimals.',
            ['max_decimals' => 2],
        ];
        yield 'too large' => [
            static fn(): InvalidValue => InvalidValue::tooLarge('money'),
            'money.too_large',
            'Invalid money: the value exceeds the supported range.',
            [],
        ];
    }

    /**
     * @param Closure(): InvalidValue $factory
     * @param array<string, scalar>   $params
     */
    #[DataProvider('provideNamedConstructors')]
    public function testNamedConstructors(Closure $factory, string $code, string $message, array $params): void
    {
        $exception = $factory();

        static::assertSame($code, $exception->violationCode());
        static::assertSame($message, $exception->getMessage());
        static::assertSame($params, $exception->params());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
    }

    public function testItConvertsToAnErrorViolation(): void
    {
        $violation = InvalidValue::tooLong('invoice_number', 50)->toViolation('lines[1].invoiceNumber');

        static::assertSame('lines[1].invoiceNumber', $violation->path());
        static::assertSame('invoice_number.too_long', $violation->code());
        static::assertSame('Invalid invoice_number: the value must not exceed 50 characters.', $violation->message());
        static::assertSame(Severity::Error, $violation->severity());
        static::assertSame(['max_length' => 50], $violation->params());
    }
}
