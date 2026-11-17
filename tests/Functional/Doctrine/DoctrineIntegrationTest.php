<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\NativeLazyOrmConfiguration;

/**
 * Source: reference package (DoctrineIntegrationTest).
 */
final class DoctrineIntegrationTest extends TestCase
{
    public function testItRegistersEveryTypeOnceAndIsIdempotent(): void
    {
        DoctrineIntegration::registerTypes();
        DoctrineIntegration::registerTypes();

        static::assertCount(10, DoctrineIntegration::types());
        foreach (DoctrineIntegration::types() as $name => $class) {
            static::assertStringStartsWith(DoctrineIntegration::TABLE_PREFIX, $name);
            static::assertSame(Type::getType($name)::class, $class);
            static::assertSame($name, Type::lookupName(Type::getType($name)));
        }
    }

    #[RunInSeparateProcess]
    public function testItRegistersTheMissingTypesWhenSomeAreAlreadyRegistered(): void
    {
        $names = array_keys(DoctrineIntegration::types());
        $first = $names[0];
        static::assertFalse(Type::hasType($first));
        Type::addType($first, DoctrineIntegration::types()[$first]);

        DoctrineIntegration::registerTypes();

        foreach ($names as $name) {
            static::assertTrue(Type::hasType($name));
        }
    }

    public function testTheMappingDirectoryHoldsOneFilePerMappedClass(): void
    {
        $directory = DoctrineIntegration::mappingDirectory();
        $files = glob($directory . '/*.orm.xml');

        static::assertDirectoryExists($directory);
        static::assertIsArray($files);
        static::assertCount(\count(self::mappedClasses()), $files);
        static::assertContains('Domain\\Submission\\Submission', self::mappedClasses());
        static::assertContains('Domain\\Submission\\SubmissionIdentity', self::mappedClasses());
    }

    public function testTheMetadataDriverMapsTheAggregateAndIgnoresOtherClasses(): void
    {
        $driver = DoctrineIntegration::mappingDriver();

        static::assertFalse($driver->isTransient(Submission::class));
        static::assertFalse($driver->isTransient(SubmissionIdentity::class));
        static::assertTrue($driver->isTransient(DoctrineIntegration::class));
    }

    public function testItProvidesTheDoctrineBundleConfiguration(): void
    {
        static::assertSame(
            [
                'dbal' => ['types' => DoctrineIntegration::types()],
                'orm' => [
                    'mappings' => [
                        'UrssafPlateformeNumerique' => [
                            'type' => 'xml',
                            'is_bundle' => false,
                            'dir' => DoctrineIntegration::mappingDirectory(),
                            'prefix' => 'TheBeardedBear\UrssafPlateformeNumerique',
                            'alias' => 'UrssafPlateformeNumerique',
                        ],
                    ],
                ],
            ],
            DoctrineIntegration::doctrineBundleConfigurationWith([]),
        );
    }

    public function testItAddsTheGivenOrmOptionsToTheBundleConfiguration(): void
    {
        $configuration = DoctrineIntegration::doctrineBundleConfigurationWith(['enable_native_lazy_objects' => true]);

        static::assertTrue($configuration['orm']['enable_native_lazy_objects'] ?? false);
        static::assertSame(DoctrineIntegration::doctrineBundleConfigurationWith([])['orm']['mappings'], $configuration['orm']['mappings']);
        static::assertSame(DoctrineIntegration::types(), $configuration['dbal']['types']);
    }

    public function testTheNativeLazyObjectsAreDetectedFromTheRuntimeByDefault(): void
    {
        static::assertSame(
            DoctrineIntegration::nativeLazyObjectsAvailable(),
            \array_key_exists('enable_native_lazy_objects', DoctrineIntegration::doctrineBundleConfiguration()['orm']),
        );
        static::assertSame(
            DoctrineIntegration::nativeLazyObjectsOptions(\PHP_VERSION_ID, Configuration::class) !== [],
            DoctrineIntegration::nativeLazyObjectsAvailable(),
        );
        static::assertSame(
            \PHP_VERSION_ID >= 80_400 && (new ReflectionClass(Configuration::class))->hasMethod('enableNativeLazyObjects'),
            DoctrineIntegration::nativeLazyObjectsAvailable(),
        );
    }

    public function testTheNativeLazyObjectsNeedPhp84AndAnOrmThatImplementsThem(): void
    {
        $enabled = ['enable_native_lazy_objects' => true];

        static::assertSame($enabled, DoctrineIntegration::nativeLazyObjectsOptions(80_400, NativeLazyOrmConfiguration::class));
        static::assertSame($enabled, DoctrineIntegration::nativeLazyObjectsOptions(80_500, NativeLazyOrmConfiguration::class));
        static::assertSame([], DoctrineIntegration::nativeLazyObjectsOptions(80_399, NativeLazyOrmConfiguration::class));
        static::assertSame([], DoctrineIntegration::nativeLazyObjectsOptions(80_400, stdClass::class));
    }

    /**
     * @return list<string> mapped classes without the common namespace prefix
     */
    private static function mappedClasses(): array
    {
        $classes = DoctrineIntegration::mappingDriver()->getAllClassNames();
        $prefix = DoctrineIntegration::MAPPING_NAMESPACE . '\\';
        $names = array_map(static fn(string $class): string => substr($class, \strlen($prefix)), $classes);
        sort($names);

        return $names;
    }
}
