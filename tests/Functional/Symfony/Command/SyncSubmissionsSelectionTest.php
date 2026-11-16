<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;

final class SyncSubmissionsSelectionTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:submissions:sync';

    public function testDryRunHonoursTheLimit(): void
    {
        $harness = Harness::boot();
        Submissions::due($harness, 'tenant-1', 'ref-1');
        Submissions::due($harness, 'tenant-1', 'ref-2');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--dry-run' => true, '--limit' => '1']));

        static::assertStringContainsString('Dry run: 1 submission(s) would be processed', $tester->getDisplay());
    }

    public function testTheLimitCapsTheNumberOfSubmissions(): void
    {
        $harness = Harness::boot();
        Submissions::due($harness, 'tenant-1', 'ref-1');
        Submissions::due($harness, 'tenant-1', 'ref-2');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--limit' => '1']));

        static::assertStringContainsString('1 submission(s) processed', $tester->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_GET_IDENTIFICATION));
    }

    public function testTheTenantOptionSelectsTheTenant(): void
    {
        $harness = Harness::boot();
        Submissions::due($harness);
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-2']));
        static::assertStringContainsString('0 submission(s) processed.', $tester->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_GET_IDENTIFICATION));
        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-1']));
        static::assertStringContainsString('1 submission(s) processed', $tester->getDisplay());
    }

    public function testEveryConfiguredTenantIsProcessed(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%', 'platform_id' => 'platform-2']]]);
        Submissions::due($harness, 'tenant-1', 'ref-1');
        Submissions::due($harness, 'tenant-2', 'ref-2');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertStringContainsString('2 submission(s) processed (changed: 2).', $tester->getDisplay());
    }
}
