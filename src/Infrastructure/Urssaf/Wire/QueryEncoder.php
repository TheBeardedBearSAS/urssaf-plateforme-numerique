<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;

/**
 * Query parameters of the search and news feed operations, as ordered name / value pairs (a name may repeat:
 * `sort`, ADR 0002). Absent criteria are not sent.
 */
final class QueryEncoder
{
    /**
     * Identification search (section 2.3): `search`, paging, `start_date`, `end_date`; never `type`.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function identificationSearch(SubmissionSearch $search): array
    {
        return [
            ...self::keyword($search),
            ...self::pageRequest($search->pageRequest()),
            ...self::dates($search),
        ];
    }

    /**
     * Declaration search (section 2.7): the identification parameters plus `type`.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function declarationSearch(SubmissionSearch $search): array
    {
        $type = $search->type();

        return [
            ...self::identificationSearch($search),
            ...($type instanceof DeclarationType ? [['type', $type->value]] : []),
        ];
    }

    /**
     * News feed (section 2.9): paging only.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function pageRequest(PageRequest $request): array
    {
        $page = $request->page();
        $size = $request->size();

        return [
            ...($page === null ? [] : [['page', (string) $page]]),
            ...($size === null ? [] : [['size', (string) $size]]),
            ...array_map(
                static fn(SortOrder $sort): array => ['sort', $sort->toWire()],
                $request->sorts(),
            ),
        ];
    }

    /**
     * RFC 3986 query string, names and values percent-encoded, repeated names kept.
     *
     * @param list<array{0: string, 1: string}> $pairs
     */
    public static function toQueryString(array $pairs): string
    {
        return implode(
            '&',
            array_map(
                static fn(array $pair): string => rawurlencode($pair[0]) . '=' . rawurlencode($pair[1]),
                $pairs,
            ),
        );
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function keyword(SubmissionSearch $search): array
    {
        $keyword = $search->search();

        return $keyword === null ? [] : [['search', $keyword]];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function dates(SubmissionSearch $search): array
    {
        $start = $search->startDate();
        $end = $search->endDate();

        return [
            ...($start instanceof CalendarDate ? [['start_date', $start->format()]] : []),
            ...($end instanceof CalendarDate ? [['end_date', $end->format()]] : []),
        ];
    }
}
