<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Benchmark;

use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\DeclarationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\IdentificationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\NewsFeedMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\UploadResponseMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

/**
 * Decoding of the URSSAF responses from the raw JSON string: the news feed, an upload response and the largest
 * search page one call accepts (500 items).
 */
final readonly class WireDecodingBench
{
    private const PAGE_SIZE = 500;

    private DateTimeZone $apiTimeZone;

    private string $newsFeed;

    private string $uploadResponse;

    private string $declarationPage;

    private string $identificationPage;

    public function __construct()
    {
        $this->apiTimeZone = new DateTimeZone(ApiConfiguration::DEFAULT_API_TIME_ZONE);
        $this->newsFeed = WireFixtures::load('news_feed_response');
        $this->uploadResponse = WireFixtures::load('upload_response');
        $this->declarationPage = self::fullPage('search_declarations_response_handbuilt');
        $this->identificationPage = self::fullPage('search_identifications_response_handbuilt');
    }

    public function benchDecodeTheOfficialNewsFeed(): void
    {
        NewsFeedMapper::mapList(JsonDecoder::decode($this->newsFeed));
    }

    public function benchDecodeAnUploadResponse(): void
    {
        UploadResponseMapper::urssafReference(JsonDecoder::decode($this->uploadResponse));
    }

    public function benchDecodeAFullDeclarationSearchPage(): void
    {
        DeclarationMapper::mapList(JsonDecoder::decode($this->declarationPage), $this->apiTimeZone);
    }

    public function benchDecodeAFullIdentificationSearchPage(): void
    {
        IdentificationMapper::mapList(JsonDecoder::decode($this->identificationPage), $this->apiTimeZone);
    }

    /**
     * Repeats the hand-built items of a fixture up to the maximum page size, each with its own `urssafRef`.
     */
    private static function fullPage(string $fixture): string
    {
        /** @var list<array<string, mixed>> $items */
        $items = json_decode(WireFixtures::load($fixture), true, 512, JSON_THROW_ON_ERROR);
        $page = [];
        for ($index = 0; $index < self::PAGE_SIZE; ++$index) {
            $item = $items[$index % \count($items)];
            $item['urssafRef'] = \sprintf('urssaf-ref-%04d', $index);
            $page[] = $item;
        }

        return json_encode($page, JSON_THROW_ON_ERROR);
    }
}
