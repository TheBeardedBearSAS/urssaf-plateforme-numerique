<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support;

use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;
use Psr\Http\Message\RequestInterface;

/**
 * Scripted URSSAF platform behind the mocked PSR-18 client (docs/api-reference.md §2, official examples). Every route
 * answers a success by default and can be switched to a given status or to a transport failure. Requests are recorded
 * per route. A request that matches no route gets an empty 200 answer from the mock client.
 */
final class HttpScript
{
    public const ROUTE_AUTHENTICATION = 'authentication';
    public const ROUTE_UPLOAD_IDENTIFICATION = 'upload_identification';
    public const ROUTE_GET_IDENTIFICATION = 'get_identification';
    public const ROUTE_SEARCH_IDENTIFICATIONS = 'search_identifications';
    public const ROUTE_DOWNLOAD_IDENTIFICATION_FILE = 'download_identification_file';
    public const ROUTE_UPLOAD_DECLARATION = 'upload_declaration';
    public const ROUTE_GET_DECLARATION = 'get_declaration';
    public const ROUTE_SEARCH_DECLARATIONS = 'search_declarations';
    public const ROUTE_DOWNLOAD_DECLARATION_FILE = 'download_declaration_file';
    public const ROUTE_NEWS_FEED = 'news_feed';

    /** "urssafRef" of the official upload response example (api-reference §2.1). */
    public const IDENTIFICATION_REFERENCE = 'cbcf2700-110d-4abf-9835-a9b46e68d7c4';

    /** "urssafRef" of the official declaration example (api-reference §2, "Declaration"). */
    public const DECLARATION_REFERENCE = 'unique-file-submission-id-12345';

    /** "fileId" of the official global report example. */
    public const GLOBAL_REPORT_FILE_ID = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

    public const REPORT_CONTENT = '<report>fictitious</report>';

    /** @var array<string, RouteEndpoint> */
    private array $routes;

    public function __construct(Client $client)
    {
        $pages = ['UCN-Total-Pages' => '1', 'UCN-Total-Count' => '1', 'UCN-Current-Page' => '1', 'UCN-Per-Page' => '20'];
        $download = ['Content-Type' => 'application/octet-stream'];
        $this->routes = [
            self::ROUTE_AUTHENTICATION => new RouteEndpoint(200, '{"access_token":"fictitious-access-token","token_type":"Bearer","expires_in":3600}'),
            self::ROUTE_UPLOAD_IDENTIFICATION => new RouteEndpoint(200, self::uploadBody(self::IDENTIFICATION_REFERENCE)),
            self::ROUTE_GET_IDENTIFICATION => new RouteEndpoint(200, self::identificationBody('ACCEPTED')),
            self::ROUTE_SEARCH_IDENTIFICATIONS => new RouteEndpoint(200, '[' . self::identificationBody('ACCEPTED') . ']', $pages),
            self::ROUTE_DOWNLOAD_IDENTIFICATION_FILE => new RouteEndpoint(200, self::REPORT_CONTENT, $download),
            self::ROUTE_UPLOAD_DECLARATION => new RouteEndpoint(200, self::uploadBody(self::DECLARATION_REFERENCE)),
            self::ROUTE_GET_DECLARATION => new RouteEndpoint(200, self::declarationBody('ACCEPTED', 'PROCESSED')),
            self::ROUTE_SEARCH_DECLARATIONS => new RouteEndpoint(200, '[' . self::declarationBody('ACCEPTED', 'PROCESSED') . ']', $pages),
            self::ROUTE_DOWNLOAD_DECLARATION_FILE => new RouteEndpoint(200, self::REPORT_CONTENT, $download),
            self::ROUTE_NEWS_FEED => new RouteEndpoint(200, self::newsFeedBody(), $pages),
        ];
        $matchers = [
            self::ROUTE_AUTHENTICATION => new RequestMatcher('/oauth/v1/token$', null, ['POST']),
            self::ROUTE_UPLOAD_IDENTIFICATION => new RequestMatcher('/identifications/upload$', null, ['POST']),
            self::ROUTE_DOWNLOAD_IDENTIFICATION_FILE => new RequestMatcher('/identifications/[^/]+/file/[^/]+$', null, ['GET']),
            self::ROUTE_GET_IDENTIFICATION => new RequestMatcher('/identifications/[^/]+$', null, ['GET']),
            self::ROUTE_SEARCH_IDENTIFICATIONS => new RequestMatcher('/identifications$', null, ['GET']),
            self::ROUTE_UPLOAD_DECLARATION => new RequestMatcher('/declarations/upload$', null, ['POST']),
            self::ROUTE_DOWNLOAD_DECLARATION_FILE => new RequestMatcher('/declarations/[^/]+/file/[^/]+$', null, ['GET']),
            self::ROUTE_GET_DECLARATION => new RequestMatcher('/declarations/[^/]+$', null, ['GET']),
            self::ROUTE_SEARCH_DECLARATIONS => new RequestMatcher('/declarations$', null, ['GET']),
            self::ROUTE_NEWS_FEED => new RequestMatcher('/news-feed$', null, ['GET']),
        ];
        foreach ($matchers as $route => $matcher) {
            $client->on($matcher, $this->routes[$route]);
        }
    }

    public function calls(string $route): int
    {
        return $this->routes[$route]->calls();
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(string $route): array
    {
        return $this->routes[$route]->requests();
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(string $route, int $status, string $body, array $headers = []): void
    {
        $this->routes[$route]->respond($status, $body, $headers);
    }

    public function failTransport(string $route): void
    {
        $this->routes[$route]->failTransport();
    }

    public static function uploadBody(string $urssafReference): string
    {
        return \sprintf('{"urssafRef":"%s"}', $urssafReference);
    }

    /**
     * The official identification example (tests/Fixtures/wire/identification_response_handbuilt.json, fictitious
     * uploader) for {@see self::IDENTIFICATION_REFERENCE}, with the given processing status.
     */
    public static function identificationBody(string $status): string
    {
        return self::fixture('identification_response_handbuilt.json', ['cd48ba8a-3a7e-4b80-8e87-f14a5542c6cf' => self::IDENTIFICATION_REFERENCE, '"ACCEPTED"' => '"' . $status . '"']);
    }

    /**
     * The official declaration example (tests/Fixtures/wire/declaration_response_handbuilt.json, fictitious uploader)
     * with the given processing and payment statuses.
     */
    public static function declarationBody(string $status, string $paymentStatus): string
    {
        return self::fixture('declaration_response_handbuilt.json', ['"ACCEPTED"' => '"' . $status . '"', '"PROCESSED"' => '"' . $paymentStatus . '"']);
    }

    public static function newsFeedBody(): string
    {
        return self::fixture('news_feed_response.json', []);
    }

    /**
     * @param array<string, string> $replacements
     */
    private static function fixture(string $file, array $replacements): string
    {
        $content = file_get_contents(\dirname(__DIR__, 3) . '/Fixtures/wire/' . $file);
        \assert(\is_string($content), 'The wire fixtures are readable.');

        return strtr($content, $replacements);
    }
}
