<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

interface CredentialsProvider
{
    /**
     * @throws RecordNotFound "credentials.not_found" when the tenant has no credentials
     */
    public function forTenant(TenantId $tenantId): Credentials;
}
