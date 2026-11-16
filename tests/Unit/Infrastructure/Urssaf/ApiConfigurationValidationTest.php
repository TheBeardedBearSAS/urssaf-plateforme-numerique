<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;

final class ApiConfigurationValidationTest extends TestCase
{
    public function testItAcceptsAZeroSafetyMargin(): void
    {
        static::assertSame(0, (new ApiConfiguration(Environment::Sandbox, null, null, 'FR', 0))->tokenSafetyMarginSeconds());
    }

    public function testItRejectsANegativeSafetyMargin(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('token_safety_margin');

        new ApiConfiguration(Environment::Sandbox, null, null, 'FR', -1);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLanguages(): iterable
    {
        yield 'lower case' => ['fr'];
        yield 'mixed case' => ['En'];
        yield 'other language' => ['DE'];
        yield 'empty' => [''];
        yield 'padded' => ['FR '];
    }

    #[DataProvider('invalidLanguages')]
    public function testItRejectsAnAcceptLanguageOtherThanFrOrEnInUpperCase(string $language): void
    {
        try {
            new ApiConfiguration(Environment::Sandbox, null, null, $language);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame('accept_language.invalid_format', $exception->violationCode());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHeaderNames(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['X Request'];
        yield 'colon' => ['X-Request:'];
        yield 'new line' => ["X-Request\n"];
        yield 'new line inside' => ["X-Re\nquest"];
        yield 'non ascii' => ['X-Requête'];
    }

    #[DataProvider('invalidHeaderNames')]
    public function testItRejectsAnInvalidRequestIdHeaderName(string $name): void
    {
        try {
            new ApiConfiguration(Environment::Sandbox, null, $name);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame('request_id_header.invalid_format', $exception->violationCode());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validHeaderNames(): iterable
    {
        yield 'usual' => ['X-Request-Id'];
        yield 'with dot' => ['recouv.request.id'];
        yield 'token characters' => ["!#$%&'*+-.^_`|~09azAZ"];
    }

    #[DataProvider('validHeaderNames')]
    public function testItAcceptsAHeaderNameMadeOfTokenCharacters(string $name): void
    {
        static::assertSame($name, (new ApiConfiguration(Environment::Sandbox, null, $name))->requestIdHeader());
    }
}
