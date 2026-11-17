<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Search;

/**
 * Direction of a sort criterion ("field:ASC" / "field:DESC").
 */
enum SortDirection: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}
