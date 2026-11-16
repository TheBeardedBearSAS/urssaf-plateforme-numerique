<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;

/**
 * One method per behaviour of the simulator (searches).
 */
final class SimulatedPlatformGatewaySearchTest extends TestCase
{
    public function testASearchServesTheSubmissionsOfTheTenantAndPlatformInUploadOrder(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202503_a.xml.gz');
        $urssaf->uploadDeclaration(SimulatorFixtures::otherTenant(), SimulatorFixtures::platform(), DeclarationType::Declaration, SimulatorFixtures::file('DPDE_202503_other-tenant.xml.gz'));
        $urssaf->uploadDeclaration(SimulatorFixtures::tenant(), SimulatorFixtures::otherPlatform(), DeclarationType::Declaration, SimulatorFixtures::file('DPDE_202503_other-platform.xml.gz'));
        SimulatorFixtures::uploadIdentification($urssaf, 'DPID_202503_ident.xml.gz');
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202501_b.xml.gz');

        $page = SearchResults::search($urssaf, SubmissionSearch::all());

        static::assertSame(['DPDE_202503_a.xml.gz', 'DPDE_202501_b.xml.gz'], SearchResults::names($page->items()));
        static::assertSame([Operation::SearchDeclarations], \array_slice($urssaf->calls(), -1));
    }

    public function testIdentificationsAreSearchedApart(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202503_a.xml.gz');
        SimulatorFixtures::uploadIdentification($urssaf, 'DPID_202503_ident.xml.gz');
        $urssaf->uploadIdentification(SimulatorFixtures::otherTenant(), SimulatorFixtures::platform(), SimulatorFixtures::file('DPID_202503_other.xml.gz'));

        $page = $urssaf->searchIdentifications(SimulatorFixtures::tenant(), SimulatorFixtures::platform(), SubmissionSearch::all());

        static::assertCount(1, $page);
        static::assertSame('DPID_202503_ident.xml.gz', $page->items()[0]->identificationFileName());
        static::assertSame('IN_PROCESS', $page->items()[0]->processingStatus()->raw());
        static::assertSame(Operation::SearchIdentifications, $urssaf->calls()[3]);
    }

    public function testASearchFiltersOnTheType(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202503_a.xml.gz');
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPRE_202503_b.xml.gz', DeclarationType::Regularisation);

        $regularisations = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::firstPage(), null, null, null, DeclarationType::Regularisation));
        $declarations = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::firstPage(), null, null, null, DeclarationType::Declaration));

        static::assertSame(['DPRE_202503_b.xml.gz'], SearchResults::names($regularisations->items()));
        static::assertSame(['DPDE_202503_a.xml.gz'], SearchResults::names($declarations->items()));
    }

    public function testASearchFiltersOnTheKeywordInTheFileNameIgnoringTheCase(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202503_Alpha.xml.gz');
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202503_beta.xml.gz');

        $found = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::firstPage(), 'alpha', null, null, null));
        $none = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::firstPage(), 'gamma', null, null, null));

        static::assertSame(['DPDE_202503_Alpha.xml.gz'], SearchResults::names($found->items()));
        static::assertTrue($none->isEmpty());
    }

    public function testASearchFiltersOnTheWithholdingPeriodBounds(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        foreach (['202501', '202502', '202503'] as $month) {
            SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_' . $month . '_x.xml.gz');
        }

        $from = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::firstPage(), null, CalendarDate::fromString('2025-02-28'), null, null));
        $until = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::firstPage(), null, null, CalendarDate::fromString('2025-02-01'), null));
        $between = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::firstPage(), null, CalendarDate::fromString('2025-02-01'), CalendarDate::fromString('2025-02-28'), null));

        static::assertSame(['DPDE_202502_x.xml.gz', 'DPDE_202503_x.xml.gz'], SearchResults::names($from->items()));
        static::assertSame(['DPDE_202501_x.xml.gz', 'DPDE_202502_x.xml.gz'], SearchResults::names($until->items()));
        static::assertSame(['DPDE_202502_x.xml.gz'], SearchResults::names($between->items()));

        $lastDay = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::firstPage(), null, CalendarDate::fromString('2025-01-31'), CalendarDate::fromString('2025-01-01'), null));
        static::assertSame(['DPDE_202501_x.xml.gz'], SearchResults::names($lastDay->items()));
    }

    public function testTheKeywordIsMatchedIgnoringTheCaseOfAccentedLetters(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        SimulatorFixtures::uploadDeclaration($urssaf, 'DPDE_202503_ÉTÉ.xml.gz');

        $found = SearchResults::search($urssaf, SubmissionSearch::of(PageRequest::firstPage(), 'été', null, null, null));

        static::assertCount(1, $found);
    }

    public function testAQueuedFailureIsThrownByTheSearch(): void
    {
        $urssaf = SimulatorFixtures::gateway();
        $urssaf->failNext(Operation::SearchDeclarations, new RuntimeException('down'));

        $this->expectExceptionMessage('down');

        SearchResults::search($urssaf, SubmissionSearch::all());
    }
}
