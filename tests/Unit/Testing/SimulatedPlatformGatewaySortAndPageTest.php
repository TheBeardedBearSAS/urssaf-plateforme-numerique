<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\FixedClock;

/**
 * One method per behaviour of the simulator (searches).
 */
final class SimulatedPlatformGatewaySortAndPageTest extends TestCase
{
    public function testASearchSortsOnThePeriodInBothDirections(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        foreach (['202502', '202503', '202501'] as $month) {
            SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_' . $month . '_x.xml.gz');
        }

        $ascending = SearchResults::sorted($urssaf, SortOrder::of('period', SortDirection::Asc));
        $descending = SearchResults::sorted($urssaf, SortOrder::of('period', SortDirection::Desc));

        static::assertSame(['DPDE_202501_x.xml.gz', 'DPDE_202502_x.xml.gz', 'DPDE_202503_x.xml.gz'], $ascending);
        static::assertSame(['DPDE_202503_x.xml.gz', 'DPDE_202502_x.xml.gz', 'DPDE_202501_x.xml.gz'], $descending);
    }

    public function testTheNextSortBreaksTheTiesOfThePrevious(): void
    {
        $clock = new FixedClock('2026-03-10T08:00:00Z');
        $urssaf = SimulatorFixtures::gateway($clock);
        foreach (['202502_a' => '08:00', '202501_b' => '09:00', '202502_c' => '10:00', '202501_d' => '11:00'] as $suffix => $time) {
            $clock->moveTo('2026-03-10T' . $time . ':00Z');
            SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_' . $suffix . '.xml.gz');
        }

        $names = SearchResults::sorted($urssaf, SortOrder::of('period', SortDirection::Asc), SortOrder::of('unknown', SortDirection::Desc), SortOrder::of('lastUpdateDate', SortDirection::Desc));

        static::assertSame(['DPDE_202501_d.xml.gz', 'DPDE_202501_b.xml.gz', 'DPDE_202502_c.xml.gz', 'DPDE_202502_a.xml.gz'], $names);
    }

    public function testASearchSortsOnTheLastUpdateDate(): void
    {
        $clock = new FixedClock('2026-03-10T08:00:00Z');
        $urssaf = SimulatorFixtures::gateway($clock);
        $first = SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202501_first.xml.gz');
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202501_second.xml.gz');
        $clock->moveTo('2026-03-10T09:00:00Z');
        $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $first);

        $ascending = SearchResults::sorted($urssaf, SortOrder::of('lastUpdateDate', SortDirection::Asc));
        $descending = SearchResults::sorted($urssaf, SortOrder::of('lastUpdateDate', SortDirection::Desc));

        static::assertSame(['DPDE_202501_second.xml.gz', 'DPDE_202501_first.xml.gz'], $ascending);
        static::assertSame(['DPDE_202501_first.xml.gz', 'DPDE_202501_second.xml.gz'], $descending);
    }

    public function testAnUnknownSortFieldKeepsTheUploadOrder(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        foreach (['202503', '202501', '202502', '202504'] as $month) {
            SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_' . $month . '_x.xml.gz');
        }

        $names = SearchResults::sorted($urssaf, SortOrder::of('unknown', SortDirection::Desc));

        static::assertSame(['DPDE_202503_x.xml.gz', 'DPDE_202501_x.xml.gz', 'DPDE_202502_x.xml.gz', 'DPDE_202504_x.xml.gz'], $names);
    }

    public function testEqualKeysKeepTheUploadOrderWhateverTheNumberOfSubmissions(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        foreach (range(10, 59) as $n) {
            SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202501_n' . $n . '.xml.gz');
        }

        $names = SearchResults::names(SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::of(1, 50, SortOrder::of('period', SortDirection::Asc)), null, null, null, null))->items());

        static::assertSame(array_map(static fn(int $n): string => 'DPDE_202501_n' . $n . '.xml.gz', range(10, 59)), $names);
    }

    public function testTwoSubmissionsOfAnUnknownSortFieldKeepTheUploadOrder(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202502_x.xml.gz');
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202501_x.xml.gz');

        $names = SearchResults::sorted($urssaf, SortOrder::of('unknown', SortDirection::Asc));

        static::assertSame(['DPDE_202502_x.xml.gz', 'DPDE_202501_x.xml.gz'], $names);
    }

    public function testASearchIsPaginated(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        foreach (range(1, 5) as $n) {
            SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202501_n' . $n . '.xml.gz');
        }

        $second = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::of(2, 2), null, null, null, null));
        $last = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::of(3, 2), null, null, null, null));
        $beyond = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::of(4, 2), null, null, null, null));

        static::assertSame(['DPDE_202501_n3.xml.gz', 'DPDE_202501_n4.xml.gz'], SearchResults::names($second->items()));
        static::assertSame([3, 5, 2, 2], SearchResults::figures($second));
        static::assertSame(['DPDE_202501_n5.xml.gz'], SearchResults::names($last->items()));
        static::assertTrue($beyond->isEmpty());
        static::assertSame([3, 5, 4, 2], SearchResults::figures($beyond));
    }

    public function testTheDefaultPageIsTheFirstOneOfTwentyItems(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        foreach (range(1, 21) as $n) {
            SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202501_n' . $n . '.xml.gz');
        }

        $page = SearchResults::search($urssaf, SubmissionSearch::all());

        static::assertCount(20, $page);
        static::assertSame('DPDE_202501_n1.xml.gz', $page->items()[0]->declarationFileName());
        static::assertSame([2, 21, 1, 20], SearchResults::figures($page));
    }

    public function testAnEmptySearchHasNoPage(): void
    {
        $page = SearchResults::search(SimulatorFixtures::gateway(), SubmissionSearch::all());

        static::assertSame([0, 0, 1, 20], SearchResults::figures($page));
    }

    public function testEverySubmissionOfThePageIsReadOnceAndTheOthersAreNot(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $urssaf->progressThrough(FileStatus::InProcess, FileStatus::Accepted);
        $first = SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202501_a.xml.gz');
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202501_b.xml.gz');

        $firstSearch = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::of(1, 1), null, null, null, null));
        $secondSearch = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::of(1, 2), null, null, null, null));

        static::assertSame(['IN_PROCESS'], SearchResults::statuses($firstSearch));
        static::assertSame(['ACCEPTED', 'IN_PROCESS'], SearchResults::statuses($secondSearch));
        static::assertSame('ACCEPTED', $urssaf->getDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $first)->processingStatus()->raw());
    }
}
