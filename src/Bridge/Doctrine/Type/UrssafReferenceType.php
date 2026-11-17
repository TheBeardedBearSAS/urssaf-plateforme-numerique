<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Column type of {@see UrssafReference}: a VARCHAR holding its string value (the "urssafRef").
 */
final class UrssafReferenceType extends Type
{
    public const NAME = 'urssaf_pn_urssaf_reference';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, UrssafReference::class, self::class, static fn(UrssafReference $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UrssafReference
    {
        return StringValueConversion::toPhp($value, self::class, UrssafReference::fromString(...));
    }
}
