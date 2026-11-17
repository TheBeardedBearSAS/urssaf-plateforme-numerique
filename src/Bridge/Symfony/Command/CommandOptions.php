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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * The options shared by the console commands (--tenant, --limit, --dry-run, --format): their declaration and their
 * reading. The tenants ({@see CommandTenants}) and the untyped inputs ({@see CommandInputs}) have their own classes. A
 * bad value raises an {@see InvalidOptionException}: the command exits with the "invalid usage" status (2).
 */
final class CommandOptions
{
    public const FORMAT_TABLE = 'table';
    public const FORMAT_JSON = 'json';

    public static function addTenant(Command $command, string $description): void
    {
        $command->addOption('tenant', null, InputOption::VALUE_REQUIRED, $description);
    }

    public static function addLimit(Command $command): void
    {
        $command->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of records to process (capped by the workflow policy)');
    }

    public static function addDryRun(Command $command): void
    {
        $command->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be done without calling URSSAF or changing anything');
    }

    public static function addFormat(Command $command): void
    {
        $command->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: table or json', self::FORMAT_TABLE);
    }

    public static function dryRun(InputInterface $input): bool
    {
        return $input->getOption('dry-run') === true;
    }

    /**
     * @throws InvalidOptionException when the format is neither "table" nor "json"
     */
    public static function format(InputInterface $input): string
    {
        $format = CommandInputs::text($input, 'format');

        return $format === self::FORMAT_TABLE || $format === self::FORMAT_JSON
            ? $format
            : throw new InvalidOptionException('The --format option must be "table" or "json".');
    }

    /**
     * @throws InvalidOptionException for a value that is not a positive integer
     */
    public static function limit(InputInterface $input): ?int
    {
        return CommandInputs::positiveInteger($input, 'limit');
    }
}
