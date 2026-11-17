<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

use SensitiveParameter;

/**
 * Cache of access tokens. Keys are opaque strings built by the authenticator (one per tenant and environment);
 * implementations must not log token values.
 */
interface TokenStore
{
    public function get(string $key): ?AccessToken;

    public function save(string $key, #[SensitiveParameter] AccessToken $token): void;

    public function delete(string $key): void;
}
