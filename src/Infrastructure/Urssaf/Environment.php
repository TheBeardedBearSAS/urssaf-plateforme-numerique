<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf;

/**
 * Target of the URSSAF "API portail Plateforme numérique" (ADR 0001): the two real-exchange platforms and the technical
 * test API ("Bac à sable"). The host stays overridable by {@see ApiConfiguration}; the base path and the scope follow
 * the target.
 */
enum Environment: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';
    case TestApi = 'test_api';

    public function baseUri(): string
    {
        return match ($this) {
            self::Production, self::TestApi => 'https://api.urssaf.fr',
            self::Sandbox => 'https://api-edi.urssaf.fr',
        };
    }

    public function basePath(): string
    {
        return match ($this) {
            self::Production, self::Sandbox => UrssafEndpoints::API_BASE_PATH,
            self::TestApi => UrssafEndpoints::TEST_API_BASE_PATH,
        };
    }

    public function scope(): string
    {
        return match ($this) {
            self::Production, self::Sandbox => UrssafEndpoints::TOKEN_SCOPE,
            self::TestApi => UrssafEndpoints::TEST_API_TOKEN_SCOPE,
        };
    }

    /**
     * The news feed is not published on the test API (api-reference section 2.9).
     */
    public function supportsNewsFeed(): bool
    {
        return match ($this) {
            self::Production, self::Sandbox => true,
            self::TestApi => false,
        };
    }
}
