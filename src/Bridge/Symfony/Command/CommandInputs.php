<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command;

use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Typed reading of the arguments and options of the commands (Symfony returns them untyped). A missing or bad value
 * raises an {@see InvalidOptionException}: the command exits with the "invalid usage" status (2).
 */
final class CommandInputs
{
    private const POSITIVE = '/^[1-9][0-9]{0,8}$/D';

    /**
     * The value of an option, null when it is not given.
     */
    public static function text(InputInterface $input, string $name): ?string
    {
        // @mago-expect analysis:mixed-assignment Symfony options are untyped: the value is narrowed on the next line.
        $value = $input->getOption($name);

        return \is_string($value) ? $value : null;
    }

    /**
     * The value of a required option.
     *
     * @throws InvalidOptionException when the option is missing
     */
    public static function requiredText(InputInterface $input, string $name): string
    {
        return self::text($input, $name) ?? throw new InvalidOptionException(\sprintf('The --%s option is required.', $name));
    }

    /**
     * The value of a required argument.
     *
     * @throws InvalidOptionException when the argument is missing
     */
    public static function requiredArgument(InputInterface $input, string $name): string
    {
        // @mago-expect analysis:mixed-assignment Symfony arguments are untyped: the value is narrowed on the next line.
        $value = $input->getArgument($name);

        return \is_string($value) ? $value : throw new InvalidOptionException(\sprintf('The "%s" argument is required.', $name));
    }

    /**
     * A positive integer option, null when it is not given.
     *
     * @throws InvalidOptionException for a value that is not a positive integer
     */
    public static function positiveInteger(InputInterface $input, string $name): ?int
    {
        $text = self::text($input, $name);
        if ($text === null) {
            return null;
        }

        // No filter_var: the bridge does not require ext-filter.
        return preg_match(self::POSITIVE, $text) === 1 ? (int) $text : throw new InvalidOptionException(\sprintf('The --%s option must be a positive integer.', $name));
    }
}
