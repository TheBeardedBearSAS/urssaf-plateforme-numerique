<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support;

/**
 * Stands for an ORM configuration that implements the native lazy objects.
 */
final class NativeLazyOrmConfiguration
{
    public function enableNativeLazyObjects(bool $enabled): bool
    {
        return $enabled;
    }
}
