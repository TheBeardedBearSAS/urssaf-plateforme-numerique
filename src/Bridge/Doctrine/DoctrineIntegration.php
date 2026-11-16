<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use ReflectionClass;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\ContentFingerprintType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\FileNameType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\PlatformIdType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\SubmissionErrorsType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\SubmissionIdType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\SubmissionSnapshotType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\UrssafReferenceType;

/**
 * Everything a host application needs to persist the submissions (and the outbox) with Doctrine ORM 3 / DBAL 4
 * (ADR 0011).
 *
 * The domain model carries no Doctrine metadata: the mapping lives in XML files (Resources/mapping) whose names follow
 * the Symfony "simplified" convention for the namespace prefix {@see self::MAPPING_NAMESPACE}. A plain application does
 *
 *     DoctrineIntegration::registerTypes();
 *     $configuration->setMetadataDriverImpl(DoctrineIntegration::mappingDriver());
 *
 * and a Symfony application prepends {@see self::doctrineBundleConfiguration()} to the "doctrine" extension. Tables,
 * types, indexes and the mapping alias are prefixed so that nothing collides with the other URSSAF packages.
 *
 * Source: reference package (DoctrineIntegration).
 */
final class DoctrineIntegration
{
    /**
     * Namespace prefix of the mapped classes (Domain and Bridge entities): file "Domain.Submission.Submission.orm.xml" maps
     * "<prefix>\Domain\Submission\Submission".
     */
    public const MAPPING_NAMESPACE = 'TheBeardedBear\UrssafPlateformeNumerique';

    public const MAPPING_ALIAS = 'UrssafPlateformeNumerique';

    /**
     * Prefix of the tables created by the mapping.
     */
    public const TABLE_PREFIX = 'urssaf_pn_';

    public static function mappingDirectory(): string
    {
        return __DIR__ . '/Resources/mapping';
    }

    /**
     * Metadata driver for the mapping files, for applications that configure the ORM without the Symfony bundle.
     */
    public static function mappingDriver(): SimplifiedXmlDriver
    {
        return new SimplifiedXmlDriver([self::mappingDirectory() => self::MAPPING_NAMESPACE]);
    }

    /**
     * The custom DBAL types of the mapping, by registration name.
     *
     * @return array<non-empty-string, class-string<Type>>
     */
    public static function types(): array
    {
        return [
            SubmissionIdType::NAME => SubmissionIdType::class,
            TenantIdType::NAME => TenantIdType::class,
            PlatformIdType::NAME => PlatformIdType::class,
            ExternalReferenceType::NAME => ExternalReferenceType::class,
            FileNameType::NAME => FileNameType::class,
            ContentFingerprintType::NAME => ContentFingerprintType::class,
            UrssafReferenceType::NAME => UrssafReferenceType::class,
            InstantType::NAME => InstantType::class,
            SubmissionSnapshotType::NAME => SubmissionSnapshotType::class,
            SubmissionErrorsType::NAME => SubmissionErrorsType::class,
        ];
    }

    /**
     * Registers the custom types in the global DBAL registry. Idempotent.
     */
    public static function registerTypes(): void
    {
        foreach (self::types() as $name => $class) {
            if (Type::hasType($name)) {
                continue;
            }
            Type::addType($name, $class);
        }
    }

    /**
     * Whether native lazy objects (PHP 8.4 lazy objects, used instead of generated proxies) can be enabled: they need
     * PHP 8.4 and an ORM that implements them (3.4+).
     */
    public static function nativeLazyObjectsAvailable(): bool
    {
        return self::runtimeNativeLazyObjectsOptions() !== [];
    }

    /**
     * The "orm" options that enable the native lazy objects, or none when the runtime cannot use them.
     *
     * @param class-string $ormConfiguration the ORM configuration class, which has "enableNativeLazyObjects()" from ORM 3.4
     *
     * @return array{enable_native_lazy_objects?: true}
     */
    public static function nativeLazyObjectsOptions(int $phpVersionId, string $ormConfiguration): array
    {
        if ($phpVersionId >= 80_400 && (new ReflectionClass($ormConfiguration))->hasMethod('enableNativeLazyObjects')) {
            return ['enable_native_lazy_objects' => true];
        }

        return [];
    }

    /**
     * Configuration to prepend to the "doctrine" extension of doctrine-bundle: the types, the XML mapping of the
     * default entity manager (or of the entity manager named by the host, under "orm.entity_managers.<name>.mappings")
     * and, when PHP and the ORM support them, the native lazy objects (symfony/var-exporter dropped the lazy ghosts the
     * ORM proxies rely on in version 8). The host can still override it in its own "doctrine" configuration.
     *
     * @return array{
     *     dbal: array{types: array<non-empty-string, class-string<Type>>},
     *     orm: array{enable_native_lazy_objects?: true, mappings: array<string, array{type: string, is_bundle: bool, dir: string, prefix: string, alias: string}>}
     * }
     */
    public static function doctrineBundleConfiguration(): array
    {
        return self::doctrineBundleConfigurationWith(self::runtimeNativeLazyObjectsOptions());
    }

    /**
     * The configuration to prepend, with the given options on top of the "orm" section.
     *
     * @param array{enable_native_lazy_objects?: true} $ormOptions
     *
     * @return array{
     *     dbal: array{types: array<non-empty-string, class-string<Type>>},
     *     orm: array{enable_native_lazy_objects?: true, mappings: array<string, array{type: string, is_bundle: bool, dir: string, prefix: string, alias: string}>}
     * }
     */
    public static function doctrineBundleConfigurationWith(array $ormOptions): array
    {
        return [
            'dbal' => ['types' => self::types()],
            'orm' => $ormOptions + [
                'mappings' => [
                    self::MAPPING_ALIAS => [
                        'type' => 'xml',
                        'is_bundle' => false,
                        'dir' => self::mappingDirectory(),
                        'prefix' => self::MAPPING_NAMESPACE,
                        'alias' => self::MAPPING_ALIAS,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{enable_native_lazy_objects?: true}
     */
    private static function runtimeNativeLazyObjectsOptions(): array
    {
        return self::nativeLazyObjectsOptions(\PHP_VERSION_ID, Configuration::class);
    }
}
