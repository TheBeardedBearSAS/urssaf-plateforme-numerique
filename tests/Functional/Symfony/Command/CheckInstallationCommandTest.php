<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;

final class CheckInstallationCommandTest extends TestCase
{
    public function testReportsEveryCheckInOrderAndWarningsDoNotFail(): void
    {
        $tester = new CommandTester(new CheckInstallationCommand([
            new RecordingCheck([new CheckResult('credentials', 'tenant-1', CheckStatus::Ok, 'Credentials found.')]),
            new RecordingCheck([
                new CheckResult('lock', '', CheckStatus::Warning, 'Disabled.'),
                new CheckResult('scheduler', '', CheckStatus::Skipped, 'Not enabled.'),
            ]),
        ], ['tenant-1']));

        static::assertSame(0, $tester->execute([]));

        static::assertSame([
            ['Check', 'Subject', 'Status', 'Detail'],
            ['credentials', 'tenant-1', 'ok', 'Credentials found.'],
            ['lock', '', 'warning', 'Disabled.'],
            ['scheduler', '', 'skipped', 'Not enabled.'],
        ], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('The URSSAF integration is ready.', $tester->getDisplay());
        static::assertStringNotContainsString('failed', $tester->getDisplay());
    }

    public function testAFailureMakesTheCommandFail(): void
    {
        $tester = new CommandTester(new CheckInstallationCommand([
            new RecordingCheck([
                new CheckResult('credentials', 'tenant-1', CheckStatus::Failure, 'Missing.'),
                new CheckResult('authentication', 'tenant-1', CheckStatus::Failure, 'Refused.'),
                new CheckResult('lock', '', CheckStatus::Ok, 'Fine.'),
            ]),
        ], ['tenant-1']));

        static::assertSame(1, $tester->execute([]));

        static::assertStringContainsString('2 check(s) failed.', $tester->getDisplay());
        static::assertStringNotContainsString('ready', $tester->getDisplay());
    }

    public function testChecksTheConfiguredTenantsWithRemoteCalls(): void
    {
        $check = RecordingCheck::ok('credentials');
        $tester = new CommandTester(new CheckInstallationCommand([$check], ['tenant-1', 'tenant-2']));

        static::assertSame(0, $tester->execute([]));

        $context = $check->context();
        static::assertNotNull($context);
        static::assertSame(['tenant-1', 'tenant-2'], array_map(strval(...), $context->tenants));
        static::assertTrue($context->remoteCallsAllowed);
    }

    public function testTheTenantOptionSelectsTheTenant(): void
    {
        $check = RecordingCheck::ok('credentials');
        $tester = new CommandTester(new CheckInstallationCommand([$check], ['tenant-1', 'tenant-2']));

        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-9']));

        $context = $check->context();
        static::assertNotNull($context);
        static::assertSame(['tenant-9'], array_map(strval(...), $context->tenants));
    }

    public function testSkipRemoteForbidsTheRemoteCalls(): void
    {
        $check = RecordingCheck::ok('credentials');
        $tester = new CommandTester(new CheckInstallationCommand([$check], ['tenant-1']));

        static::assertSame(0, $tester->execute(['--skip-remote' => true]));

        $context = $check->context();
        static::assertNotNull($context);
        static::assertFalse($context->remoteCallsAllowed);
    }

    public function testNeedsATenant(): void
    {
        $tester = new CommandTester(new CheckInstallationCommand([RecordingCheck::ok('credentials')], []));

        static::assertSame(2, $tester->execute([]));
        static::assertStringContainsString('No tenant to process: use --tenant=<id> or configure "urssaf_plateforme_numerique.tenants".', $tester->getDisplay());
    }

    public function testChecksTheInstallationOfTheApplication(): void
    {
        $tester = Harness::boot(['lock' => ['enabled' => false]])->command('urssaf:pn:check');

        static::assertSame(0, $tester->execute(['--skip-remote' => true]));

        static::assertStringContainsString('The URSSAF integration is ready.', $tester->getDisplay());
    }
}
