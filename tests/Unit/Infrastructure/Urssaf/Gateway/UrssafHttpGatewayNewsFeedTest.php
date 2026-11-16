<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Gateway;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http\HttpStacks;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

final class UrssafHttpGatewayNewsFeedTest extends TestCase
{
    public function testTheNewsFeedIsReadWithItsPagingOnly(): void
    {
        $harness = GatewayHarness::sandbox();
        $harness->answer(200, WireFixtures::load('news_feed_response'), ['UCN-Total-Count' => '2']);

        $page = $harness->gateway->newsFeed(
            HttpStacks::tenant(),
            GatewayHarness::platform(),
            PageRequest::of(1, 20, SortOrder::of('newsDate', SortDirection::Asc)),
        );

        static::assertCount(2, $page);
        static::assertSame('45eea586-2dac-4dbd-a173-aadfc7eda6d8', $page->items()[0]->idNews());
        static::assertSame(2, $page->info()->totalCount());
        static::assertSame(
            GatewayHarness::BASE . '/platforms/platform-1/news-feed?page=1&size=20&sort=newsDate%3AASC',
            (string) $harness->stack->lastRequest()->getUri(),
        );
    }

    public function testTheTestApiHasNoNewsFeedAndNothingIsSent(): void
    {
        $harness = GatewayHarness::on(Environment::TestApi);

        try {
            $harness->gateway->newsFeed(HttpStacks::tenant(), GatewayHarness::platform(), PageRequest::firstPage());
            static::fail('Expected the operation to be refused.');
        } catch (OperationNotSupported) {
            static::assertSame([], $harness->stack->requests());
            static::assertSame([], $harness->stack->rateLimiter->acquired());
        }
    }

    public function testTheTestApiServesTheOtherOperationsFromItsOwnBasePath(): void
    {
        $harness = GatewayHarness::on(Environment::TestApi);
        $harness->answer(200, '{}');

        $harness->gateway->getIdentification(HttpStacks::tenant(), GatewayHarness::platform(), GatewayHarness::reference());

        static::assertSame(
            'https://api.urssaf.fr/api-odspn-be-platforms-sandbox/v1/platforms/platform-1/identifications/unique-file-submission-id-12345',
            (string) $harness->stack->lastRequest()->getUri(),
        );
    }
}
