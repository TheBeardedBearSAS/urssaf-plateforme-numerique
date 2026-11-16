<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic;

use Override;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ServiceIds;

/**
 * The Symfony Scheduler schedule ("scheduler.enabled").
 */
final readonly class SchedulerCheck implements InstallationCheck
{
    private const NAME = 'scheduler';

    public function __construct(private bool $enabled) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if (!$this->enabled) {
            return [new CheckResult(self::NAME, '', CheckStatus::Skipped, 'Disabled ("scheduler.enabled"): run the commands from a crontab (docs/symfony.md).')];
        }

        return [new CheckResult(self::NAME, '', CheckStatus::Ok, \sprintf('Schedule "%s": consume it with "messenger:consume scheduler_%1$s".', ServiceIds::SCHEDULE))];
    }
}
