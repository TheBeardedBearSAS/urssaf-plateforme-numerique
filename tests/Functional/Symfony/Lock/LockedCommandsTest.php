<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Lock;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command\ScratchFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class LockedCommandsTest extends TestCase
{
    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('commands')]
    public function testACommandIsSkippedWhenItsOperationIsAlreadyRunning(string $command, array $options, string $resource): void
    {
        $harness = Harness::boot([], false, false, true);
        Scenario::uploadedIdentification($harness);
        $harness->clock->sleep(700);
        $held = HeldLocks::hold($harness, $resource);
        $setupRequests = \count($harness->client->getRequests());

        $tester = $harness->command($command);

        static::assertSame(0, $tester->execute($options));
        static::assertStringContainsString(\sprintf('Already running (lock "%s" is held): skipped.', $resource), $tester->getDisplay());
        static::assertCount($setupRequests, $harness->client->getRequests());
        static::assertTrue($held->isAcquired());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function commands(): iterable
    {
        $identification = ScratchFiles::file(Scenario::IDENTIFICATION_FILE, 'another fictitious content');
        $declaration = ScratchFiles::file(Scenario::DECLARATION_FILE, 'another fictitious content');

        yield 'upload identification' => ['urssaf:pn:identification:upload', ['file' => $identification, '--external-reference' => 'ref-9'], 'urssaf_pn.identification.upload.tenant-1'];
        yield 'upload declaration' => ['urssaf:pn:declaration:upload', ['file' => $declaration, '--type' => 'DECLARATION', '--external-reference' => 'ref-9'], 'urssaf_pn.declaration.upload.tenant-1.DECLARATION'];
        yield 'upload regularisation' => ['urssaf:pn:declaration:upload', ['file' => $declaration, '--type' => 'REGULARISATION', '--external-reference' => 'ref-9'], 'urssaf_pn.declaration.upload.tenant-1.REGULARISATION'];
        yield 'sync' => ['urssaf:pn:submissions:sync', [], 'urssaf_pn.submissions.sync.tenant-1'];
        yield 'sync of a tenant' => ['urssaf:pn:submissions:sync', ['--tenant' => 'tenant-1'], 'urssaf_pn.submissions.sync.tenant-1'];
        yield 'reconcile' => ['urssaf:pn:submissions:reconcile', [], 'urssaf_pn.submissions.reconcile.tenant-1'];
        yield 'reconcile of a tenant' => ['urssaf:pn:submissions:reconcile', ['--tenant' => 'tenant-1'], 'urssaf_pn.submissions.reconcile.tenant-1'];
    }

    public function testADeclarationOfAnotherTypeIsNotBlocked(): void
    {
        $harness = Harness::boot([], false, false, true);
        $held = HeldLocks::hold($harness, 'urssaf_pn.declaration.upload.tenant-1.DECLARATION');
        $tester = $harness->command('urssaf:pn:declaration:upload');

        $status = $tester->execute(['file' => ScratchFiles::file(Scenario::REGULARISATION_FILE, Scenario::FILE_CONTENT), '--type' => 'REGULARISATION', '--external-reference' => 'ref-1']);

        static::assertSame(0, $status);
        static::assertStringNotContainsString('Already running', $tester->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_UPLOAD_DECLARATION));
        static::assertTrue($held->isAcquired());
    }

    public function testAnotherTenantIsStillProcessed(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%', 'platform_id' => 'platform-2']]], false, false, true);
        Scenario::uploadedIdentification($harness, 'tenant-1', 'ref-1');
        $second = Scenario::uploadedIdentification($harness, 'tenant-2', 'ref-2')->submissionId->value();
        $harness->clock->sleep(700);
        $held = HeldLocks::hold($harness, 'urssaf_pn.submissions.sync.tenant-1');
        $tester = $harness->command('urssaf:pn:submissions:sync');

        static::assertSame(0, $tester->execute([]));

        static::assertTrue($held->isAcquired());
        static::assertStringContainsString('Already running (lock "urssaf_pn.submissions.sync.tenant-1"', $tester->getDisplay());
        static::assertStringContainsString($second, $tester->getDisplay());
        static::assertStringContainsString('1 submission(s) processed (changed: 1).', $tester->getDisplay());
    }

    public function testACommandRunsAndReleasesItsLockWhenFree(): void
    {
        $harness = Harness::boot([], false, false, true);
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $harness->clock->sleep(700);
        $tester = $harness->command('urssaf:pn:submissions:sync');

        static::assertSame(0, $tester->execute([]));

        static::assertStringContainsString($id, $tester->getDisplay());
        static::assertStringNotContainsString('Already running', $tester->getDisplay());
        static::assertTrue(HeldLocks::factory($harness)->createLock('urssaf_pn.submissions.sync.tenant-1')->acquire());
    }

    public function testADryRunDoesNotTakeTheLock(): void
    {
        $harness = Harness::boot([], false, false, true);
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $harness->clock->sleep(700);
        $held = HeldLocks::hold($harness, 'urssaf_pn.submissions.sync.tenant-1');
        $tester = $harness->command('urssaf:pn:submissions:sync');

        static::assertSame(0, $tester->execute(['--dry-run' => true]));

        static::assertStringContainsString($id, $tester->getDisplay());
        static::assertTrue($held->isAcquired());
        static::assertStringNotContainsString('Already running', $tester->getDisplay());
    }

    public function testTheLockCanBeDisabled(): void
    {
        $harness = Harness::boot(['lock' => ['enabled' => false]], false, false, true);
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $harness->clock->sleep(700);
        $held = HeldLocks::hold($harness, 'urssaf_pn.submissions.sync.tenant-1');
        $tester = $harness->command('urssaf:pn:submissions:sync');

        static::assertSame(0, $tester->execute([]));

        static::assertStringContainsString($id, $tester->getDisplay());
        static::assertTrue($held->isAcquired());
        static::assertStringNotContainsString('Already running', $tester->getDisplay());
    }

    public function testTheLockIsOptionalWhenTheFactoryDoesNotExist(): void
    {
        $harness = Harness::boot(['lock' => ['factory' => 'my.lock_factory']]);
        $id = Scenario::uploadedIdentification($harness)->submissionId->value();
        $harness->clock->sleep(700);
        $tester = $harness->command('urssaf:pn:submissions:sync');

        static::assertSame(0, $tester->execute([]));

        static::assertStringContainsString($id, $tester->getDisplay());
    }
}
