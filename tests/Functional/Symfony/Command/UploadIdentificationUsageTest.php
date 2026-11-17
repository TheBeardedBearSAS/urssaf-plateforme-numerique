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
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class UploadIdentificationUsageTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:identification:upload';

    public function testTheTenantIsRequiredWhenSeveralAreConfigured(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%', 'platform_id' => 'platform-2']]]);
        $tester = $harness->command(self::COMMAND);
        $path = ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT);

        static::assertSame(2, $tester->execute(['file' => $path, '--external-reference' => 'ref-1']));
        static::assertStringContainsString('The --tenant option is required (configured tenants: 2).', $tester->getDisplay());
        static::assertSame(0, $tester->execute(['file' => $path, '--external-reference' => 'ref-1', '--tenant' => 'tenant-2']));
    }

    public function testRefusesAMissingExternalReference(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT)]));
        static::assertStringContainsString('The --external-reference option is required.', $tester->getDisplay());
    }

    public function testRefusesAnUnreadableFile(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['file' => ScratchFiles::directory() . '/missing.xml.gz', '--external-reference' => 'ref-1']));
        static::assertStringContainsString('file_source.unreadable', $tester->getDisplay());
    }

    public function testRefusesAnInvalidName(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['file' => ScratchFiles::file(Scenario::IDENTIFICATION_FILE, Scenario::FILE_CONTENT), '--external-reference' => 'ref-1', '--name' => '']));
        static::assertStringContainsString('file_name', $tester->getDisplay());
    }
}
