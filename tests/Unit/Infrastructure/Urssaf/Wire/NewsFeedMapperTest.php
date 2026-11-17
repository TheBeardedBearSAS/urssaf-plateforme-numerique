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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\NewsFeedMapper;

final class NewsFeedMapperTest extends TestCase
{
    public function testItMapsTheOfficialExample(): void
    {
        $items = NewsFeedMapper::mapList(WireFixtures::fixture('news_feed_response'));

        static::assertCount(2, $items);
        static::assertSame('45eea586-2dac-4dbd-a173-aadfc7eda6d8', $items[0]->idNews());
        static::assertSame('Sandbox PNID_2025-11_4309999_001.xml.gz: new reports available.', $items[0]->newsText());
        static::assertSame('2026-03-25T15:35:23Z', $items[0]->newsDate()?->format());
        static::assertSame('9bc3d9fd-80a0-420e-85e4-f94e9668cdff', $items[1]->idNews());
        static::assertSame('2026-03-25T15:13:44Z', $items[1]->newsDate()?->format());
    }

    public function testNewsDateHonoursItsOffset(): void
    {
        $item = NewsFeedMapper::map(WireFixtures::payload(['newsDate' => '2026-03-25T15:35:23+02:00']));

        static::assertSame('2026-03-25T13:35:23Z', $item->newsDate()?->format());
    }

    public function testEveryFieldIsOptional(): void
    {
        $item = NewsFeedMapper::map(WireFixtures::payload([]));

        static::assertNull($item->idNews());
        static::assertNull($item->newsText());
        static::assertNull($item->newsDate());
    }

    public function testMapListOfAnEmptyArray(): void
    {
        static::assertSame([], NewsFeedMapper::mapList(WireFixtures::payload([])));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidItems(): iterable
    {
        yield 'id not a string' => [['idNews' => 1]];
        yield 'text not a string' => [['newsText' => []]];
        yield 'date not a string' => [['newsDate' => 1]];
        yield 'date without offset' => [['newsDate' => '2026-03-25T15:35:23']];
        yield 'date not RFC 3339' => [['newsDate' => 'today']];
        yield 'date that does not exist' => [['newsDate' => '2026-02-30T15:35:23Z']];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidItems')]
    public function testItRejectsInvalidItems(array $body): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        NewsFeedMapper::map(WireFixtures::payload($body));
    }

    public function testADateErrorKeepsItsCause(): void
    {
        try {
            NewsFeedMapper::map(WireFixtures::payload(['newsDate' => 'today']));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertStringContainsString('field "newsDate" is not an RFC 3339 date-time', $exception->getMessage());
            static::assertNotNull($exception->getPrevious());
        }
    }

    public function testMapListRejectsAnObject(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        NewsFeedMapper::mapList(WireFixtures::payload(['a' => 1]));
    }
}
