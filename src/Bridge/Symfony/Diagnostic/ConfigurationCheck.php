<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic;

use Closure;
use Override;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use Throwable;

/**
 * The API configuration can be built (environment, accept language, time zone...): an invalid value, typically an
 * environment variable resolved at runtime, is reported here instead of preventing the command from running.
 */
final readonly class ConfigurationCheck implements InstallationCheck
{
    private const NAME = 'configuration';

    /**
     * @param Closure(): ApiConfiguration $configuration builds the configuration lazily, so that a failure is reported
     */
    public function __construct(private Closure $configuration) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        // Any failure of the build (unknown environment, invalid value resolved from an environment variable...) is reported.
        try {
            $configuration = ($this->configuration)();
        } catch (Throwable $failure) {
            return [new CheckResult(self::NAME, '', CheckStatus::Failure, $failure->getMessage())];
        }

        return [new CheckResult(self::NAME, '', CheckStatus::Ok, \sprintf(
            'Environment "%s", base URI "%s", API time zone "%s".',
            $configuration->environment()->value,
            $configuration->baseUri(),
            $configuration->apiTimeZone()->getName(),
        ))];
    }
}
