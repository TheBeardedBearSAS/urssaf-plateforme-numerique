<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Diagnostic;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\DatabaseSchemaCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;

final class DatabaseSchemaCheckTest extends TestCase
{
    public function testTheDatabaseSchemaMustBeCreated(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine']], true);
        $check = $harness->privateService(DatabaseSchemaCheck::class, DatabaseSchemaCheck::class);

        $missing = $check->run(self::context());
        static::assertCount(2, $missing);
        static::assertSame(['database schema', 'default', CheckStatus::Failure], [$missing[0]->check, $missing[0]->subject, $missing[0]->status]);
        static::assertMatchesRegularExpression('/^\d+ schema change\(s\) pending on the package tables: run doctrine:migrations:diff then doctrine:migrations:migrate\.$/', $missing[0]->detail);
        static::assertEquals(
            new CheckResult('entity manager', 'default', CheckStatus::Warning, 'Shared with the application: use a dedicated entity manager (docs/doctrine.md).'),
            $missing[1],
        );

        // A table of the application in the same database is not a pending change of the package tables.
        $harness->privateService('doctrine.dbal.default_connection', Connection::class)->executeStatement('CREATE TABLE host_invoices (id INTEGER NOT NULL)');
        $harness->createDatabaseSchema();
        static::assertEquals(
            [
                new CheckResult('database schema', 'default', CheckStatus::Ok, 'The package tables match the mapping.'),
                new CheckResult('entity manager', 'default', CheckStatus::Warning, 'Shared with the application: use a dedicated entity manager (docs/doctrine.md).'),
            ],
            $check->run(self::context()),
        );
    }

    public function testADedicatedEntityManagerIsNotReported(): void
    {
        $doctrine = ['dbal' => ['driver' => 'pdo_sqlite', 'memory' => true], 'orm' => ['entity_managers' => ['urssaf' => ['connection' => 'default']]]];
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']], $doctrine);

        $results = $harness->privateService(DatabaseSchemaCheck::class, DatabaseSchemaCheck::class)->run(self::context());

        static::assertCount(1, $results);
        static::assertSame(['database schema', 'urssaf'], [$results[0]->check, $results[0]->subject]);
    }

    private static function context(): CheckContext
    {
        return CheckContext::withoutRemoteCalls([TenantId::fromString('tenant-1')]);
    }
}
