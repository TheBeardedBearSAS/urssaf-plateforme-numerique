<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\ContentFingerprintType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\FileNameType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\PlatformIdType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\SubmissionIdType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\UrssafReferenceType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ContentFingerprint;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Conversion scenarios of the string-backed column types (reference StringValueTypesTest).
 */
final class StringValueTypesTest extends TestCase
{
    private const FINGERPRINT = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    /**
     * @return iterable<string, array{string, string, string}> type name, stored value, an invalid stored value
     */
    public static function types(): iterable
    {
        yield 'submission id' => [SubmissionIdType::NAME, 'submission-1', ''];
        yield 'tenant id' => [TenantIdType::NAME, 'tenant-1', '   '];
        yield 'platform id' => [PlatformIdType::NAME, 'platform-1', 'platform/1'];
        yield 'external reference' => [ExternalReferenceType::NAME, 'host-1', ''];
        yield 'file name' => [FileNameType::NAME, 'file-1.zip', 'file"1.zip'];
        yield 'urssaf reference' => [UrssafReferenceType::NAME, 'urssaf-ref-1', 'urssaf/ref'];
        yield 'content fingerprint' => [ContentFingerprintType::NAME, self::FINGERPRINT, 'not-a-fingerprint'];
    }

    #[DataProvider('types')]
    public function testItStoresTheValueAndRebuildsTheValueObject(string $name, string $stored): void
    {
        $type = Type::getType($name);
        $value = self::valueObject($name, $stored);

        static::assertSame($stored, $type->convertToDatabaseValue($value, $this->platform));
        static::assertEquals($value, $type->convertToPHPValue($stored, $this->platform));
    }

    #[DataProvider('types')]
    public function testNullStaysNull(string $name): void
    {
        $type = Type::getType($name);

        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
    }

    #[DataProvider('types')]
    public function testItRefusesAForeignPhpValue(string $name, string $stored): void
    {
        $foreign = new class implements Stringable {
            public function __toString(): string
            {
                return 'foreign';
            }
        };

        try {
            Type::getType($name)->convertToDatabaseValue($foreign, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . self::valueObject($name, $stored)::class . ', null.', $exception->getMessage());
        }
    }

    #[DataProvider('types')]
    public function testItRefusesAStoredValueThatIsNotAString(string $name): void
    {
        try {
            Type::getType($name)->convertToPHPValue(12, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: string, null.', $exception->getMessage());
        }
    }

    #[DataProvider('types')]
    public function testItReportsAStoredValueTheValueObjectRefuses(string $name, string $stored, string $invalid): void
    {
        try {
            Type::getType($name)->convertToPHPValue($invalid, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringContainsString(Type::getType($name)::class, $exception->getMessage());
            static::assertStringContainsString('Expected format "valid value"', $exception->getMessage());
            static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
        }
    }

    #[DataProvider('types')]
    public function testItIsAVarcharOfTheColumnLength(string $name): void
    {
        static::assertSame('VARCHAR(64)', Type::getType($name)->getSQLDeclaration(['length' => 64], $this->platform));
    }

    /**
     * @throws InvalidValue
     */
    private static function valueObject(string $typeName, string $stored): object
    {
        return match ($typeName) {
            SubmissionIdType::NAME => SubmissionId::fromString($stored),
            TenantIdType::NAME => TenantId::fromString($stored),
            PlatformIdType::NAME => PlatformId::fromString($stored),
            ExternalReferenceType::NAME => ExternalReference::fromString($stored),
            FileNameType::NAME => FileName::fromString($stored),
            UrssafReferenceType::NAME => UrssafReference::fromString($stored),
            default => ContentFingerprint::fromHex($stored),
        };
    }
}
