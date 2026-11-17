<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection;

/**
 * Ids of the private services of the bundle that have no interface to be identified by, and the names the bundle
 * registers its services under (ADR 0012: every name is prefixed, nothing is shared with another URSSAF bundle).
 */
final class ServiceIds
{
    public const SYMFONY_HTTP_CLIENT = 'urssaf_plateforme_numerique.http_client';
    public const PSR18_CLIENT = 'urssaf_plateforme_numerique.psr18_client';
    public const API_CONFIGURATION = 'urssaf_plateforme_numerique.api_configuration';
    public const TOKEN_PROVIDER = 'urssaf_plateforme_numerique.token_provider';

    /** Token provider of "urssaf:pn:check": its own in-memory store, so that every check asks URSSAF. */
    public const PROBE_TOKEN_PROVIDER = 'urssaf_plateforme_numerique.probe_token_provider';
    public const JSON_CLIENT = 'urssaf_plateforme_numerique.json_client';
    public const PLATFORM_EXCHANGE = 'urssaf_plateforme_numerique.platform_exchange';

    /** Monolog channel of the services that log (ADR 0012). */
    public const LOGGER_CHANNEL = 'urssaf_pn';
    public const LOGGER_TAG = 'monolog.logger';

    /** Name of the Symfony Scheduler schedule of the bundle: consumed with "messenger:consume scheduler_urssaf_pn". */
    public const SCHEDULE = 'urssaf_pn';

    /**
     * Service id of a DoctrineBundle entity manager (driver "doctrine", option "persistence.entity_manager").
     */
    public static function entityManager(string $name): string
    {
        return \sprintf('doctrine.orm.%s_entity_manager', $name);
    }
}
