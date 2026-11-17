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
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;

final class SyncSubmissionsCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:submissions:sync';

    public function testDryRunListsTheSubmissionsDueWithoutCallingUrssaf(): void
    {
        $harness = Harness::boot();
        $id = Submissions::due($harness);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--dry-run' => true]));

        static::assertSame([['Tenant', 'Submission'], ['tenant-1', $id]], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('Dry run: 1 submission(s) would be processed, URSSAF was not called.', $tester->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_GET_IDENTIFICATION));
    }

    public function testPollsTheSubmissionsInProcess(): void
    {
        $harness = Harness::boot();
        $id = Submissions::due($harness);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertSame([['Submission', 'Outcome'], [$id, 'changed']], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('1 submission(s) processed (changed: 1).', $tester->getDisplay());
        static::assertStringNotContainsString('uploader@example.com', $tester->getDisplay());
        static::assertStringNotContainsString('user-0001', $tester->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_GET_IDENTIFICATION));
        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString('0 submission(s) processed.', $tester->getDisplay());
    }

    public function testASubmissionStillInProcessIsUnchanged(): void
    {
        $harness = Harness::boot();
        Submissions::due($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_GET_IDENTIFICATION, 200, HttpScript::identificationBody('IN_PROCESS'));
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertStringContainsString('unchanged: 1', $tester->getDisplay());
    }

    public function testASubmissionUnknownToUrssafIsNotAFailure(): void
    {
        $harness = Harness::boot();
        Submissions::due($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_GET_IDENTIFICATION, 404, '{}');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertStringContainsString('not_found: 1', $tester->getDisplay());
    }

    public function testARefusedPollFailsTheCommand(): void
    {
        $harness = Harness::boot();
        Submissions::due($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_GET_IDENTIFICATION, 400, '[]');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));

        static::assertStringContainsString('failed: 1', $tester->getDisplay());
    }

    public function testRefusedCredentialsFailTheCommand(): void
    {
        $harness = Harness::boot();
        Submissions::due($harness);
        $harness->clock->sleep(7200);
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{}');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));

        static::assertStringContainsString('invalid_credentials: 1', $tester->getDisplay());
    }

    public function testATemporaryFailureFailsTheCommand(): void
    {
        $harness = Harness::boot();
        Submissions::due($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_GET_IDENTIFICATION, 429, '{}');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));

        static::assertStringContainsString('URSSAF is temporarily unavailable: the submission must be retried later.', $tester->getDisplay());
    }
}
