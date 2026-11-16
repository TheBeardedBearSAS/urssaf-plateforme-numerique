<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic;

use Override;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\BundleConfiguration;

/**
 * Where the submissions are stored ("persistence.driver").
 */
final readonly class PersistenceCheck implements InstallationCheck
{
    private const NAME = 'persistence';

    public function __construct(private string $driver) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if ($this->driver === BundleConfiguration::DRIVER_IN_MEMORY) {
            return [new CheckResult(self::NAME, $this->driver, CheckStatus::Warning, 'In-memory: nothing survives the process, use it for tests only (docs/symfony.md).')];
        }

        return [new CheckResult(self::NAME, $this->driver, CheckStatus::Ok, \sprintf('Driver "%s".', $this->driver))];
    }
}
