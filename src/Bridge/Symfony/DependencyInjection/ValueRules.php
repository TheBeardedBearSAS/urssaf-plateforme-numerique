<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use Throwable;

/**
 * Validation predicates of the scalar values of the configuration tree (each one is true when the value is invalid).
 */
final class ValueRules
{
    /**
     * The empty string is the dummy value of an environment variable placeholder while the container is validated:
     * an empty variable fails at runtime instead (Environment::from()).
     */
    public static function isUnknownEnvironment(mixed $environment): bool
    {
        return !\in_array($environment, ['', ...array_map(static fn(Environment $case): string => $case->value, Environment::cases())], true);
    }

    public static function isUnknownTimeZone(mixed $zone): bool
    {
        return !\is_string($zone) || !\in_array($zone, DateTimeZone::listIdentifiers(), true);
    }

    public static function isInvalidOptionalInterval(mixed $value): bool
    {
        return $value !== null && self::isInvalidInterval($value);
    }

    /**
     * An invalid duration, or one of zero length (WorkflowPolicy refuses a zero "stale_upload_after").
     */
    public static function isInvalidPositiveInterval(mixed $value): bool
    {
        if (!\is_string($value) || self::isInvalidInterval($value)) {
            return true;
        }

        return (new DateTimeImmutable('@0'))->add(new DateInterval($value))->getTimestamp() === 0;
    }

    public static function isInvalidInterval(mixed $value): bool
    {
        try {
            new DateInterval(\is_string($value) ? $value : '');
        } catch (Throwable) {
            return true;
        }

        return false;
    }
}
