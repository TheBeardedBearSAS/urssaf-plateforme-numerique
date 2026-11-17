<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class WorkflowStatusCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:status';

    public function testCountsTheSubmissionsByTenantAndState(): void
    {
        $harness = self::withSubmissions();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        $rows = Output::rows($tester->getDisplay());
        static::assertSame(['Tenant', ...array_map(static fn(SubmissionState $state): string => $state->value, SubmissionState::cases()), 'Needing attention'], $rows[0]);
        static::assertCount(3, $rows);
        $columns = array_flip(\array_slice($rows[0], 1, -1));
        static::assertSame('tenant-1', $rows[1][0]);
        static::assertSame('1', $rows[1][1 + $columns['in_process']]);
        static::assertSame('1', $rows[1][1 + $columns['outcome_unknown']]);
        static::assertSame('0', $rows[1][1 + $columns['accepted']]);
        static::assertSame('1', $rows[1][\count($rows[1]) - 1]);
        static::assertSame('tenant-2', $rows[2][0]);
        static::assertSame('0', $rows[2][\count($rows[2]) - 1]);
    }

    public function testPrintsJsonForMonitoring(): void
    {
        $harness = self::withSubmissions();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--format' => 'json']));

        static::assertStringContainsString("[\n    {\n        \"tenant_id\": \"tenant-1\",\n", $tester->getDisplay());
        $data = self::decode($tester->getDisplay());
        static::assertSame(['tenant-1', 'tenant-2'], array_column($data, 'tenant_id'));
        static::assertSame(1, $data[0]['needing_attention']);
        static::assertSame(1, $data[0]['states']['in_process']);
        static::assertSame(1, $data[0]['states']['outcome_unknown']);
        static::assertSame(0, $data[1]['needing_attention']);
    }

    public function testTheTenantOptionSelectsOneTenantEvenWithoutSubmissions(): void
    {
        $harness = self::withSubmissions();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-3', '--format' => 'json']));

        $data = self::decode($tester->getDisplay());
        static::assertSame(['tenant-3'], array_column($data, 'tenant_id'));
        static::assertSame(0, $data[0]['needing_attention']);
    }

    public function testRefusesAnUnknownFormat(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['--format' => 'yaml']));
        static::assertStringContainsString('The --format option must be "table" or "json".', $tester->getDisplay());
    }

    public function testNeverCallsUrssaf(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertSame([], $harness->client->getRequests());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    private static function withSubmissions(): Harness
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%', 'platform_id' => 'platform-2']]]);
        Scenario::uploadedIdentification($harness, 'tenant-1', 'ref-1');
        $harness->urssaf->failTransport(HttpScript::ROUTE_UPLOAD_IDENTIFICATION);
        Scenario::uploadedIdentification($harness, 'tenant-1', 'ref-2');
        $harness->urssaf->respond(HttpScript::ROUTE_UPLOAD_IDENTIFICATION, 200, HttpScript::uploadBody('urssaf-ref-2'));
        Scenario::uploadedIdentification($harness, 'tenant-2', 'ref-3');

        return $harness;
    }

    /**
     * @return list<array{tenant_id: string, states: array<string, int>, needing_attention: int}>
     */
    private static function decode(string $json): array
    {
        /** @var list<array{tenant_id: string, states: array<string, int>, needing_attention: int}> */
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
