<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Gateway;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http\HttpStacks;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

final class UrssafHttpGatewaySearchTest extends TestCase
{
    private const PAGING_HEADERS = [
        'UCN-Total-Pages' => '3',
        'UCN-Total-Count' => '25',
        'UCN-Current-Page' => '2',
        'UCN-Per-Page' => '10',
    ];

    public function testIdentificationsAreSearchedAndTheTypeIsNeverSent(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, WireFixtures::load('search_identifications_response_handbuilt'), self::PAGING_HEADERS);

        $page = $harness->gateway->searchIdentifications(HttpStacks::tenant(), GatewayHarness::platform(), $this->search());

        static::assertCount(2, $page);
        static::assertSame(3, $page->info()->totalPages());
        static::assertSame(25, $page->info()->totalCount());
        static::assertSame(2, $page->info()->currentPage());
        static::assertSame(10, $page->info()->perPage());
        $request = $harness->stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            GatewayHarness::BASE . '/platforms/platform-1/identifications'
            . '?search=Plateforme%20X&page=2&size=10&sort=period%3AASC&start_date=2025-04-01&end_date=2025-04-30',
            (string) $request->getUri(),
        );
    }

    public function testDeclarationsAreSearchedWithTheirType(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, WireFixtures::load('search_declarations_response_handbuilt'), self::PAGING_HEADERS);

        $page = $harness->gateway->searchDeclarations(HttpStacks::tenant(), GatewayHarness::platform(), $this->search());

        static::assertCount(2, $page);
        static::assertSame(25, $page->info()->totalCount());
        static::assertSame(
            GatewayHarness::BASE . '/platforms/platform-1/declarations'
            . '?search=Plateforme%20X&page=2&size=10&sort=period%3AASC&start_date=2025-04-01&end_date=2025-04-30&type=REGULARISATION',
            (string) $harness->stack->lastRequest()->getUri(),
        );
    }

    public function testNoCriteriaSendsNoQueryAndMissingHeadersGiveUnknownPaging(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, '[]');

        $page = $harness->gateway->searchDeclarations(HttpStacks::tenant(), GatewayHarness::platform(), SubmissionSearch::all());

        static::assertTrue($page->isEmpty());
        static::assertNull($page->info()->totalPages());
        static::assertNull($page->info()->totalCount());
        static::assertNull($page->info()->currentPage());
        static::assertNull($page->info()->perPage());
        static::assertSame(
            GatewayHarness::BASE . '/platforms/platform-1/declarations',
            (string) $harness->stack->lastRequest()->getUri(),
        );
    }

    public function testASearchRefusedWithA400IsARejection(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(400, '[{"code":"ERR_X","message":"m"}]');

        $this->expectException(UrssafRequestRejected::class);

        $harness->gateway->searchIdentifications(HttpStacks::tenant(), GatewayHarness::platform(), SubmissionSearch::all());
    }

    public function testASearchAnswerThatIsNotAListIsUnexpected(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, '{"a":1}');

        $this->expectException(UnexpectedUrssafResponse::class);

        $harness->gateway->searchDeclarations(HttpStacks::tenant(), GatewayHarness::platform(), SubmissionSearch::all());
    }

    private function search(): SubmissionSearch
    {
        return SubmissionSearch::of(
            PageRequest::of(2, 10, SortOrder::of('period', SortDirection::Asc)),
            'Plateforme X',
            CalendarDate::fromString('2025-04-01'),
            CalendarDate::fromString('2025-04-30'),
            DeclarationType::Regularisation,
        );
    }
}
