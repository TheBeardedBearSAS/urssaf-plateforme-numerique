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

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway;

/**
 * Declaration searches of the simulator tests and the figures read from their pages.
 */
final class SearchResults
{
    /**
     * @return list<string>
     */
    public static function sorted(SimulatedPlatformGateway $urssaf, SortOrder ...$sorts): array
    {
        return self::names(self::search($urssaf, SubmissionSearch::of(PageRequest::of(null, null, ...$sorts), null, null, null, null))->items());
    }

    /**
     * @return Page<DeclarationSubmission>
     */
    public static function search(SimulatedPlatformGateway $urssaf, SubmissionSearch $search): Page
    {
        return $urssaf->searchDeclarations(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), $search);
    }

    /**
     * @param list<DeclarationSubmission> $items
     *
     * @return list<string>
     */
    public static function names(array $items): array
    {
        return array_map(static fn(DeclarationSubmission $item): string => (string) $item->declarationFileName(), $items);
    }

    /**
     * @param Page<DeclarationSubmission> $page
     *
     * @return list<string>
     */
    public static function statuses(Page $page): array
    {
        return array_map(static fn(DeclarationSubmission $item): string => (string) $item->processingStatus()->raw(), $page->items());
    }

    /**
     * @param Page<DeclarationSubmission> $page
     *
     * @return list<int|null>
     */
    public static function figures(Page $page): array
    {
        $info = $page->info();

        return [$info->totalPages(), $info->totalCount(), $info->currentPage(), $info->perPage()];
    }
}
