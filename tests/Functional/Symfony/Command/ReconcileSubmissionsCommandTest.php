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
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class ReconcileSubmissionsCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:submissions:reconcile';

    public function testDryRunListsTheUnresolvedSubmissionsWithoutCallingUrssaf(): void
    {
        $harness = Harness::boot();
        $id = self::unknownOutcome($harness);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--dry-run' => true]));

        static::assertSame([['Tenant', 'Submission'], ['tenant-1', $id]], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('Dry run: 1 submission(s) would be processed, URSSAF was not called.', $tester->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_SEARCH_IDENTIFICATIONS));
    }

    public function testAdoptsTheReferenceFoundAtUrssaf(): void
    {
        $harness = Harness::boot();
        $id = self::unknownOutcome($harness);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertSame([['Submission', 'Outcome'], [$id, 'adopted']], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('1 submission(s) processed (adopted: 1).', $tester->getDisplay());
    }

    public function testAnUploadNotFoundIsLeftUnresolvedWithoutFailing(): void
    {
        $harness = Harness::boot();
        self::unknownOutcome($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_SEARCH_IDENTIFICATIONS, 200, '[]', ['UCN-Total-Pages' => '1', 'UCN-Total-Count' => '0', 'UCN-Current-Page' => '1', 'UCN-Per-Page' => '20']);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertStringContainsString('unmatched: 1', $tester->getDisplay());
    }

    public function testAnUploadUnresolvedForTooLongNeedsAManualReview(): void
    {
        $harness = Harness::boot();
        self::unknownOutcome($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_SEARCH_IDENTIFICATIONS, 200, '[]', ['UCN-Total-Pages' => '1', 'UCN-Total-Count' => '0', 'UCN-Current-Page' => '1', 'UCN-Per-Page' => '20']);
        $harness->clock->sleep(30 * 86_400);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));

        static::assertStringContainsString('manual_review: 1', $tester->getDisplay());
    }

    public function testRefusedCredentialsFailTheCommand(): void
    {
        $harness = Harness::boot();
        self::unknownOutcome($harness);
        $harness->clock->sleep(7200);
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{}');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));

        static::assertStringContainsString('invalid_credentials: 1', $tester->getDisplay());
    }

    public function testATemporaryFailureFailsTheCommand(): void
    {
        $harness = Harness::boot();
        self::unknownOutcome($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_SEARCH_IDENTIFICATIONS, 429, '{}');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));

        static::assertStringContainsString('URSSAF is temporarily unavailable: the submission must be retried later.', $tester->getDisplay());
    }

    public function testTheLimitAndTheTenantOptionsSelectTheSubmissions(): void
    {
        $harness = Harness::boot();
        self::unknownOutcome($harness, 'ref-1');
        self::unknownOutcome($harness, 'ref-2');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--dry-run' => true, '--limit' => '1']));
        static::assertStringContainsString('Dry run: 1 submission(s)', $tester->getDisplay());
        static::assertSame(0, $tester->execute(['--dry-run' => true, '--tenant' => 'tenant-2']));
        static::assertStringContainsString('Dry run: 0 submission(s)', $tester->getDisplay());
        static::assertSame(0, $tester->execute(['--dry-run' => true, '--tenant' => 'tenant-1']));
        static::assertStringContainsString('Dry run: 2 submission(s)', $tester->getDisplay());
    }

    /**
     * An identification whose upload outcome is unknown (the call failed after sending the file), found at URSSAF.
     */
    private static function unknownOutcome(Harness $harness, string $reference = Scenario::EXTERNAL_REFERENCE): string
    {
        $harness->urssaf->failTransport(HttpScript::ROUTE_UPLOAD_IDENTIFICATION);
        $id = Scenario::uploadedIdentification($harness, Scenario::TENANT, $reference)->submissionId->value();
        $harness->urssaf->respond(HttpScript::ROUTE_UPLOAD_IDENTIFICATION, 200, HttpScript::uploadBody(HttpScript::IDENTIFICATION_REFERENCE));

        return $id;
    }
}
