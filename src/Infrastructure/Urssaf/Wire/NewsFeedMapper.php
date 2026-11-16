<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Reads the `NewsFeed` items (api-reference section 2.9); `newsDate` is strict RFC 3339.
 */
final class NewsFeedMapper
{
    /**
     * @throws UnexpectedUrssafResponse when a field has the wrong type or `newsDate` is not an RFC 3339 date-time
     */
    public static function map(Payload $item): NewsItem
    {
        $date = $item->optionalString('newsDate');

        try {
            $newsDate = $date === null ? null : Instant::fromIso8601($date);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('field "newsDate" is not an RFC 3339 date-time', $exception);
        }

        return NewsItem::of($item->optionalString('idNews'), $item->optionalString('newsText'), $newsDate);
    }

    /**
     * @param Payload $list the decoded array of `NewsFeed` objects
     *
     * @return list<NewsItem>
     *
     * @throws UnexpectedUrssafResponse
     */
    public static function mapList(Payload $list): array
    {
        return array_map(self::map(...), $list->entries());
    }
}
