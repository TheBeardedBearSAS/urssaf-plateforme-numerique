<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console inputs declaring the options and the argument shared by the commands.
 */
final class TestInput
{
    private function __construct() {}

    /**
     * @param array<string, bool|string> $values
     */
    public static function of(array $values): ArrayInput
    {
        return new ArrayInput($values, new InputDefinition([
            new InputArgument('id', InputArgument::OPTIONAL),
            new InputOption('tenant', null, InputOption::VALUE_REQUIRED),
            new InputOption('limit', null, InputOption::VALUE_REQUIRED),
            new InputOption('page', null, InputOption::VALUE_REQUIRED),
            new InputOption('name', null, InputOption::VALUE_REQUIRED),
            new InputOption('dry-run', null, InputOption::VALUE_NONE),
            new InputOption('format', null, InputOption::VALUE_REQUIRED, '', 'table'),
        ]));
    }
}
