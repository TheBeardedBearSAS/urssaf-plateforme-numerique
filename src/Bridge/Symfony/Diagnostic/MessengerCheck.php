<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic;

use Override;

/**
 * The handlers of the tenant synchronisation and reconciliation messages ("messenger.enabled").
 */
final readonly class MessengerCheck implements InstallationCheck
{
    private const NAME = 'messenger';

    public function __construct(private bool $enabled, private string $bus) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if (!$this->enabled) {
            return [new CheckResult(self::NAME, '', CheckStatus::Skipped, 'Disabled ("messenger.enabled"): enable it to consume the synchronisation and reconciliation messages from a worker (docs/symfony.md).')];
        }

        return [new CheckResult(self::NAME, '', CheckStatus::Ok, \sprintf('Handlers registered, outbox bus "%s": route the messages to a transport of your own (docs/symfony.md).', $this->bus))];
    }
}
