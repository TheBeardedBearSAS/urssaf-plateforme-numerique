<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidOptionException;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CommandTenants;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

final class CommandTenantsTest extends TestCase
{
    public function testTheTenantIsReadAsATenantId(): void
    {
        static::assertNull(CommandTenants::tenant(TestInput::of([])));
        static::assertSame('tenant-1', CommandTenants::tenant(TestInput::of(['--tenant' => 'tenant-1']))?->value());
        static::assertSame('tenant-1', CommandTenants::requiredTenant(TestInput::of(['--tenant' => 'tenant-1']))->value());
    }

    public function testTheTenantMayBeRequired(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --tenant option is required.');

        CommandTenants::requiredTenant(TestInput::of([]));
    }

    public function testAnInvalidTenantIsRefusedByTheDomain(): void
    {
        $this->expectException(InvalidValue::class);

        CommandTenants::tenant(TestInput::of(['--tenant' => '']));
    }

    public function testTheTenantOrTheOnlyConfiguredTenant(): void
    {
        static::assertSame('tenant-2', CommandTenants::tenantOrSingleConfigured(TestInput::of(['--tenant' => 'tenant-2']), ['tenant-1', 'tenant-3'])->value());
        static::assertSame('tenant-1', CommandTenants::tenantOrSingleConfigured(TestInput::of([]), ['tenant-1'])->value());
    }

    /**
     * @param list<string> $configured
     */
    #[DataProvider('ambiguousTenants')]
    public function testNoTenantWhenTheConfigurationIsNotUnique(array $configured): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage(\sprintf('The --tenant option is required (configured tenants: %d).', \count($configured)));

        CommandTenants::tenantOrSingleConfigured(TestInput::of([]), $configured);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function ambiguousTenants(): iterable
    {
        yield 'none' => [[]];
        yield 'two' => [['tenant-1', 'tenant-2']];
    }

    public function testTheTenantsOrTheConfiguredTenants(): void
    {
        static::assertSame(['tenant-9'], array_map(strval(...), CommandTenants::tenantsOrConfigured(TestInput::of(['--tenant' => 'tenant-9']), ['tenant-1'])));
        static::assertSame(['tenant-1', 'tenant-2'], array_map(strval(...), CommandTenants::tenantsOrConfigured(TestInput::of([]), ['tenant-1', 'tenant-2'])));
    }

    public function testNoTenantWhenNoneIsConfigured(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('No tenant to process: use --tenant=<id> or configure "urssaf_plateforme_numerique.tenants".');

        CommandTenants::tenantsOrConfigured(TestInput::of([]), []);
    }
}
