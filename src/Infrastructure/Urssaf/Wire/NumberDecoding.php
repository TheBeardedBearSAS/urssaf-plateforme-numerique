<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Reads the JSON numbers of the API (counters and amounts, ADR 0006) as {@see DecimalNumber}, never through a
 * float calculation.
 */
final class NumberDecoding
{
    /**
     * An integer beyond the PHP range is decoded as a string by {@see JsonDecoder}; that is the only string accepted.
     */
    private const BIG_INTEGER_PATTERN = '/^-?[1-9]\d{18,}$/D';

    /**
     * @throws UnexpectedUrssafResponse when the string is not a big integer or the float cannot be written without an exponent
     */
    public static function decimal(int|float|string $value): DecimalNumber
    {
        try {
            if (\is_int($value)) {
                return DecimalNumber::fromInt($value);
            }

            if (\is_float($value)) {
                return DecimalNumber::fromFloat($value);
            }

            if (preg_match(self::BIG_INTEGER_PATTERN, $value) !== 1 || filter_var($value, FILTER_VALIDATE_INT) !== false) {
                throw UnexpectedUrssafResponse::invalidPayload('a number is expected');
            }

            return DecimalNumber::fromString($value);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('number is out of range', $exception);
        }
    }

    /**
     * The decimal value of a field, null when the field is absent.
     *
     * @throws UnexpectedUrssafResponse
     */
    public static function field(Payload $payload, string $key): ?DecimalNumber
    {
        $value = $payload->number($key);

        return $value === null ? null : self::decimal($value);
    }
}
