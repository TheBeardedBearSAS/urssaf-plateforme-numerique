<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\QueryEncoder;

final class QueryEncoderTest extends TestCase
{
    public function testNoCriteriaSendsNothing(): void
    {
        static::assertSame([], QueryEncoder::identificationSearch(SubmissionSearch::all()));
        static::assertSame([], QueryEncoder::declarationSearch(SubmissionSearch::all()));
        static::assertSame([], QueryEncoder::pageRequest(PageRequest::firstPage()));
    }

    public function testAFullIdentificationSearchNeverSendsTheType(): void
    {
        $pairs = QueryEncoder::identificationSearch($this->fullSearch());

        static::assertSame(
            [
                ['search', 'Plateforme X'],
                ['page', '2'],
                ['size', '10'],
                ['sort', 'period:ASC'],
                ['sort', 'lastUpdateDate:DESC'],
                ['start_date', '2025-04-01'],
                ['end_date', '2025-04-30'],
            ],
            $pairs,
        );
    }

    public function testAFullDeclarationSearchSendsTheTypeLast(): void
    {
        $pairs = QueryEncoder::declarationSearch($this->fullSearch());

        static::assertSame(['type', 'REGULARISATION'], $pairs[7]);
        static::assertCount(8, $pairs);
        static::assertSame(['search', 'Plateforme X'], $pairs[0]);
        static::assertSame(['end_date', '2025-04-30'], $pairs[6]);
    }

    public function testEachCriterionIsIndependent(): void
    {
        $startOnly = SubmissionSearch::of(PageRequest::firstPage(), null, CalendarDate::fromString('2025-04-01'), null, null);
        $endOnly = SubmissionSearch::of(PageRequest::firstPage(), null, null, CalendarDate::fromString('2025-04-30'), null);
        $typeOnly = SubmissionSearch::of(PageRequest::firstPage(), null, null, null, DeclarationType::Declaration);
        $searchOnly = SubmissionSearch::of(PageRequest::firstPage(), 'x', null, null, null);

        static::assertSame([['start_date', '2025-04-01']], QueryEncoder::declarationSearch($startOnly));
        static::assertSame([['end_date', '2025-04-30']], QueryEncoder::declarationSearch($endOnly));
        static::assertSame([['type', 'DECLARATION']], QueryEncoder::declarationSearch($typeOnly));
        static::assertSame([], QueryEncoder::identificationSearch($typeOnly));
        static::assertSame([['search', 'x']], QueryEncoder::identificationSearch($searchOnly));
    }

    public function testThePageRequestPairs(): void
    {
        static::assertSame([['page', '3']], QueryEncoder::pageRequest(PageRequest::of(3, null)));
        static::assertSame([['size', '500']], QueryEncoder::pageRequest(PageRequest::of(null, 500)));
        static::assertSame(
            [['sort', 'newsDate:DESC']],
            QueryEncoder::pageRequest(PageRequest::of(null, null, SortOrder::of('newsDate', SortDirection::Desc))),
        );
    }

    public function testTheQueryStringRepeatsNamesAndPercentEncodes(): void
    {
        static::assertSame('', QueryEncoder::toQueryString([]));
        static::assertSame(
            'search=Plateforme%20X%20%26%20Co%2Bmore&sort=period%3AASC&sort=lastUpdateDate%3ADESC&page=2',
            QueryEncoder::toQueryString([
                ['search', 'Plateforme X & Co+more'],
                ['sort', 'period:ASC'],
                ['sort', 'lastUpdateDate:DESC'],
                ['page', '2'],
            ]),
        );
        static::assertSame('a%20b=%C3%A9', QueryEncoder::toQueryString([['a b', 'é']]));
    }

    private function fullSearch(): SubmissionSearch
    {
        return SubmissionSearch::of(
            PageRequest::of(
                2,
                10,
                SortOrder::of('period', SortDirection::Asc),
                SortOrder::of('lastUpdateDate', SortDirection::Desc),
            ),
            'Plateforme X',
            CalendarDate::fromString('2025-04-01'),
            CalendarDate::fromString('2025-04-30'),
            DeclarationType::Regularisation,
        );
    }
}
