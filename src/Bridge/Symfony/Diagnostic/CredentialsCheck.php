<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic;

use Override;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Every tenant has credentials and a platform in the configured providers (nothing is sent to URSSAF).
 */
final readonly class CredentialsCheck implements InstallationCheck
{
    private const NAME = 'credentials';

    public function __construct(private CredentialsProvider $credentials, private PlatformResolver $platforms) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        return array_map($this->check(...), $context->tenants);
    }

    private function check(TenantId $tenantId): CheckResult
    {
        try {
            $this->credentials->forTenant($tenantId);
            $this->platforms->forTenant($tenantId);
        } catch (RecordNotFound $failure) {
            return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Failure, $failure->getMessage());
        }

        return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Ok, 'Credentials and platform found.');
    }
}
