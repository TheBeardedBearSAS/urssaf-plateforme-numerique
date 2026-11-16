<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf;

/**
 * Paths and scopes of the URSSAF "API portail Plateforme numérique" (relative to {@see Environment::baseUri()}).
 *
 * Two APIs share the same resources: "Échanges réels" (real exchanges) and "Bac à sable" (technical test API, served
 * from the production host under its own base path and scope). See docs/api-reference.md. The per-resource paths are
 * built by the gateway.
 */
final class UrssafEndpoints
{
    public const TOKEN_PATH = '/api/oauth/v1/token';
    public const TOKEN_SCOPE = 'ods.pn.platforms';
    public const API_BASE_PATH = '/api-odspn-be-platforms/v1';
    public const TEST_API_TOKEN_SCOPE = 'ods.pn.platforms.sandbox';
    public const TEST_API_BASE_PATH = '/api-odspn-be-platforms-sandbox/v1';
}
