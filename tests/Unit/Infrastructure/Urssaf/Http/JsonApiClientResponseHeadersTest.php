<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiResponse;

final class JsonApiClientResponseHeadersTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    private function call(array $headers): ApiResponse
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '[]', $headers);

        return $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get(Operation::SearchIdentifications, '/p', []),
        );
    }

    public function testTheResponseHeadersAreReadableCaseInsensitively(): void
    {
        $response = $this->call(['UCN-Total-Count' => '42', 'UCN-Total-Pages' => '2']);

        static::assertSame('42', $response->header('ucn-total-count'));
        static::assertSame('2', $response->header('UCN-TOTAL-PAGES'));
        static::assertNull($response->header('UCN-Current-Page'));
    }

    public function testTheValuesOfARepeatedHeaderAreJoined(): void
    {
        $response = $this->call(['X-Many' => 'a, b']);

        static::assertSame('a, b', $response->header('X-Many'));
    }
}
