<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;

final class ApiErrorsTest extends TestCase
{
    public function testApiError(): void
    {
        $error = ApiError::of('ERR_FICTIF', 'Compte inconnu.', 'Le compte est inconnu.');

        static::assertSame('ERR_FICTIF', $error->code());
        static::assertSame('Compte inconnu.', $error->message());
        static::assertSame('Le compte est inconnu.', $error->description());
        static::assertTrue($error->hasCode('ERR_FICTIF'));
        static::assertFalse($error->hasCode('ERR_AUTRE'));
        static::assertTrue($error->equals(ApiError::of('ERR_FICTIF', 'Compte inconnu.', 'Le compte est inconnu.')));
        static::assertFalse($error->equals(ApiError::of('X', 'Compte inconnu.', 'Le compte est inconnu.')));
        static::assertFalse($error->equals(ApiError::of('ERR_FICTIF', 'X', 'Le compte est inconnu.')));
        static::assertFalse($error->equals(ApiError::of('ERR_FICTIF', 'Compte inconnu.', 'X')));
    }

    public function testCollection(): void
    {
        $first = ApiError::of('ERR_VALIDATION_X', 'm', 'd');
        $second = ApiError::of('ERR_PARAM_X', 'm', 'd');

        $errors = ApiErrors::of($first, $second);

        static::assertFalse($errors->isEmpty());
        static::assertCount(2, $errors);
        static::assertSame([$first, $second], $errors->all());
        static::assertSame(['ERR_VALIDATION_X', 'ERR_PARAM_X'], $errors->codes());
        static::assertTrue($errors->hasCode('ERR_PARAM_X'));
        static::assertTrue($errors->hasCode('ERR_VALIDATION_X'));
        static::assertFalse($errors->hasCode('ERR_TECH_X'));
    }

    public function testEmptyCollections(): void
    {
        static::assertTrue(ApiErrors::none()->isEmpty());
        static::assertCount(0, ApiErrors::none());
        static::assertSame([], ApiErrors::none()->all());
        static::assertTrue(ApiErrors::of()->isEmpty());
        static::assertFalse(ApiErrors::none()->hasCode('X'));
    }

    public function testOfNormalisesKeys(): void
    {
        $error = ApiError::of('X', 'm', 'd');

        static::assertSame([$error], ApiErrors::of(...['named' => $error])->all());
    }
}
