<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;

/**
 * Column type of {@see FileName}: a VARCHAR holding its string value.
 */
final class FileNameType extends Type
{
    public const NAME = 'urssaf_pn_file_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, FileName::class, self::class, static fn(FileName $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?FileName
    {
        return StringValueConversion::toPhp($value, self::class, FileName::fromString(...));
    }
}
