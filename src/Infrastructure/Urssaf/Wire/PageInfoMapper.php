<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\PageInfo;

/**
 * Reads the `UCN-Total-Pages`, `UCN-Total-Count`, `UCN-Current-Page` and `UCN-Per-Page` response headers. They are
 * declared as strings: an absent header or one that is not a plain non-negative integer is unknown (ADR 0002).
 */
final class PageInfoMapper
{
    private const INTEGER_PATTERN = '/^\d{1,18}$/D';

    public static function fromHeaders(?string $totalPages, ?string $totalCount, ?string $currentPage, ?string $perPage): PageInfo
    {
        return PageInfo::of(
            self::figure($totalPages),
            self::figure($totalCount),
            self::figure($currentPage),
            self::figure($perPage),
        );
    }

    private static function figure(?string $header): ?int
    {
        return $header !== null && preg_match(self::INTEGER_PATTERN, $header) === 1 ? (int) $header : null;
    }
}
