<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * ADR 0009: the submissions table stores no personal data, in particular no acting user.
 */
final class NoPersonalDataTest extends TestCase
{
    public function testTheSubmissionsTableHasNoUserColumn(): void
    {
        $columns = TestDatabase::entityManager()->getConnection()->createSchemaManager()->introspectTableColumnsByUnquotedName('urssaf_pn_submissions');

        $names = [];
        foreach ($columns as $column) {
            $names[] = $column->getObjectName()->getIdentifier()->getValue();
        }

        static::assertContains('tenant_id', $names);
        foreach ($names as $name) {
            static::assertStringNotContainsString('user', $name);
            static::assertStringNotContainsString('mail', $name);
        }
    }
}
