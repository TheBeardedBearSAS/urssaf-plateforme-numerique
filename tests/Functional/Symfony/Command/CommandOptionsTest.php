<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CommandOptions;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;

final class CommandOptionsTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:submissions:sync';

    public function testRefusesALimitThatIsNotAPositiveInteger(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        foreach (['0', 'abc', '-1', '1.5', '', ' 1', '1 ', '1234567890', "1\n"] as $limit) {
            static::assertSame(2, $tester->execute(['--limit' => $limit]), $limit);
            static::assertStringContainsString('The --limit option must be a positive integer.', $tester->getDisplay());
        }
    }

    public function testRefusesAnInvalidTenant(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['--tenant' => str_repeat('x', 65)]));
        static::assertStringContainsString('tenant_id', $tester->getDisplay());
    }

    public function testNeedsATenantWhenNoneIsConfigured(): void
    {
        $tester = Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials', 'platform_resolver' => 'test.platforms'])->command(self::COMMAND);

        static::assertSame(2, $tester->execute([]));
        static::assertStringContainsString('No tenant to process: use --tenant=<id> or configure "urssaf_plateforme_numerique.tenants".', $tester->getDisplay());
        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-1']));
    }

    public function testTheLimitIsReadAsAnInteger(): void
    {
        static::assertNull(CommandOptions::limit(TestInput::of([])));
        static::assertSame(1, CommandOptions::limit(TestInput::of(['--limit' => '1'])));
        static::assertSame(500, CommandOptions::limit(TestInput::of(['--limit' => '500'])));
        static::assertSame(999_999_999, CommandOptions::limit(TestInput::of(['--limit' => '999999999'])));
    }

    public function testRefusesALimitOfTheDomain(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --limit option must be a positive integer.');

        CommandOptions::limit(TestInput::of(['--limit' => '0']));
    }

    public function testTheDryRunFlag(): void
    {
        static::assertFalse(CommandOptions::dryRun(TestInput::of([])));
        static::assertTrue(CommandOptions::dryRun(TestInput::of(['--dry-run' => true])));
    }

    public function testTheFormatIsTableOrJson(): void
    {
        static::assertSame('table', CommandOptions::format(TestInput::of([])));
        static::assertSame('table', CommandOptions::format(TestInput::of(['--format' => 'table'])));
        static::assertSame('json', CommandOptions::format(TestInput::of(['--format' => 'json'])));
    }

    public function testRefusesAnotherFormat(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --format option must be "table" or "json".');

        CommandOptions::format(TestInput::of(['--format' => 'csv']));
    }

    public function testTheSharedOptionsAreDeclared(): void
    {
        $command = new class extends Command {};
        CommandOptions::addTenant($command, 'The tenant.');
        CommandOptions::addLimit($command);
        CommandOptions::addDryRun($command);
        CommandOptions::addFormat($command);

        $definition = $command->getDefinition();
        static::assertSame('The tenant.', $definition->getOption('tenant')->getDescription());
        static::assertTrue($definition->getOption('tenant')->isValueRequired());
        static::assertTrue($definition->getOption('limit')->isValueRequired());
        static::assertFalse($definition->getOption('dry-run')->acceptValue());
        static::assertTrue($definition->getOption('format')->isValueRequired());
        static::assertSame('table', $definition->getOption('format')->getDefault());
    }
}
