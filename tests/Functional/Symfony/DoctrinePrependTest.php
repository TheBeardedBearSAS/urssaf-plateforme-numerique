<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\ExtensionConfigSnapshot;

final class DoctrinePrependTest extends TestCase
{
    public function testTheTypesAndTheMappingArePrependedForTheDefaultEntityManager(): void
    {
        $snapshot = new ExtensionConfigSnapshot('doctrine');

        (new TestKernel(['persistence' => ['driver' => 'doctrine']], true))->definitionsRecordedBy($snapshot);

        $prepended = $snapshot->configs()[0];
        static::assertSame(DoctrineIntegration::doctrineBundleConfiguration(), $prepended);
    }

    public function testTheDoctrineDriverIsTheDefault(): void
    {
        $snapshot = new ExtensionConfigSnapshot('doctrine');

        (new TestKernel(['persistence' => null], true))->definitionsRecordedBy($snapshot);

        static::assertSame(DoctrineIntegration::doctrineBundleConfiguration(), $snapshot->configs()[0]);
    }

    public function testTheMappingGoesToTheSelectedEntityManager(): void
    {
        $snapshot = new ExtensionConfigSnapshot('doctrine');
        $doctrine = ['dbal' => ['driver' => 'pdo_sqlite', 'memory' => true], 'orm' => ['entity_managers' => ['urssaf' => ['connection' => 'default']]]];

        (new TestKernel(['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']], $doctrine))->definitionsRecordedBy($snapshot);

        $expected = DoctrineIntegration::doctrineBundleConfiguration();
        $mappings = $expected['orm']['mappings'];
        unset($expected['orm']['mappings']);
        $expected['orm']['entity_managers'] = ['urssaf' => ['mappings' => $mappings]];
        static::assertSame($expected, $snapshot->configs()[0]);
        static::assertSame($doctrine, $snapshot->configs()[1]);
    }

    public function testNothingIsPrependedForAnotherDriver(): void
    {
        $snapshot = new ExtensionConfigSnapshot('doctrine');

        (new TestKernel(['persistence' => ['driver' => 'in_memory']], true))->definitionsRecordedBy($snapshot);

        static::assertCount(1, $snapshot->configs());
        static::assertSame(['driver' => 'pdo_sqlite', 'memory' => true], $snapshot->configs()[0]['dbal']);
    }

    public function testNothingIsPreparedForDoctrineWhenDoctrineBundleIsNotRegistered(): void
    {
        $snapshot = new ExtensionConfigSnapshot('doctrine');

        (new TestKernel(['persistence' => ['driver' => 'doctrine']]))->definitionsRecordedBy($snapshot);

        static::assertSame([], $snapshot->configs());
    }
}
