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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;

/**
 * A 404 is only declared on single-resource reads and downloads (ADR 0002).
 */
final class JsonApiClientUndeclaredNotFoundTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation}>
     */
    public static function operationsWithoutDeclared404(): iterable
    {
        yield 'search identifications' => [Operation::SearchIdentifications];
        yield 'search declarations' => [Operation::SearchDeclarations];
        yield 'news feed' => [Operation::GetNewsFeed];
    }

    #[DataProvider('operationsWithoutDeclared404')]
    public function testA404OnASearchOrTheNewsFeedIsUnexpected(Operation $operation): void
    {
        $stack = HttpStacks::sandbox();
        $stack->respondToken();
        $stack->respond(404, '[{"code":"ERR_A"}]');

        $this->expectException(UnexpectedUrssafResponse::class);

        $stack->jsonClient()->send(
            HttpStacks::tenant(),
            $stack->credentials->forTenant(HttpStacks::tenant()),
            ApiRequest::get($operation, '/p', []),
        );
    }
}
