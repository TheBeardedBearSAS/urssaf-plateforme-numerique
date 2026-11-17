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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ContentFingerprint;

/**
 * Column type of {@see ContentFingerprint}: a VARCHAR holding its hexadecimal SHA-256 digest.
 */
final class ContentFingerprintType extends Type
{
    public const NAME = 'urssaf_pn_content_fingerprint';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, ContentFingerprint::class, self::class, static fn(ContentFingerprint $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ContentFingerprint
    {
        return StringValueConversion::toPhp($value, self::class, ContentFingerprint::fromHex(...));
    }
}
