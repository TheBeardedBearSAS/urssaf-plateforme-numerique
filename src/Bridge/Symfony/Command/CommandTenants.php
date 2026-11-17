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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * Reading of the tenants of the commands: --tenant, else the configured tenants (ADR 0007).
 */
final class CommandTenants
{
    public static function tenant(InputInterface $input): ?TenantId
    {
        $tenant = CommandInputs::text($input, 'tenant');

        return $tenant === null ? null : TenantId::fromString($tenant);
    }

    /**
     * @throws InvalidOptionException when the option is missing
     */
    public static function requiredTenant(InputInterface $input): TenantId
    {
        return self::tenant($input) ?? throw new InvalidOptionException('The --tenant option is required.');
    }

    /**
     * The tenant given with --tenant, else the only configured tenant.
     *
     * @param list<string> $configured
     *
     * @throws InvalidOptionException when --tenant is missing and there is not exactly one configured tenant
     */
    public static function tenantOrSingleConfigured(InputInterface $input, array $configured): TenantId
    {
        $tenant = self::tenant($input);
        if ($tenant instanceof TenantId) {
            return $tenant;
        }
        if (\count($configured) !== 1) {
            throw new InvalidOptionException(\sprintf('The --tenant option is required (configured tenants: %d).', \count($configured)));
        }

        return TenantId::fromString($configured[0]);
    }

    /**
     * The tenant given with --tenant, else the configured tenants.
     *
     * @param list<string> $configured
     *
     * @return non-empty-list<TenantId>
     *
     * @throws InvalidOptionException when no tenant can be determined
     */
    public static function tenantsOrConfigured(InputInterface $input, array $configured): array
    {
        $tenant = self::tenant($input);
        if ($tenant instanceof TenantId) {
            return [$tenant];
        }
        $tenants = array_map(TenantId::fromString(...), $configured);

        return $tenants === [] ? throw new InvalidOptionException('No tenant to process: use --tenant=<id> or configure "urssaf_plateforme_numerique.tenants".') : $tenants;
    }
}
