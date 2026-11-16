<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Search;

/**
 * Direction of a sort criterion ("field:ASC" / "field:DESC").
 */
enum SortDirection: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}
