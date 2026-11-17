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
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Serializer\SubmissionErrorsSerializer;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;

/**
 * JSON column type of a list of {@see SubmissionError}: the codes and messages of a refused upload. A stored document
 * is never quoted in a conversion error (ADR 0009).
 */
final class SubmissionErrorsType extends Type
{
    public const NAME = 'urssaf_pn_submission_errors';

    private const EXPECTED = 'list<' . SubmissionError::class . '>';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidType::new($value, self::class, [self::EXPECTED, 'null']);
        }
        // @mago-expect analysis:mixed-assignment The list comes from an untyped column value; each item is checked below.
        foreach ($value as $error) {
            if (!$error instanceof SubmissionError) {
                throw InvalidType::new($value, self::class, [self::EXPECTED, 'null']);
            }
        }

        /** @var list<SubmissionError> $value */
        return JsonConversion::encode(SubmissionErrorsSerializer::toArray($value));
    }

    /**
     * @return list<SubmissionError>|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        return JsonConversion::decode(
            $value,
            self::class,
            self::EXPECTED,
            SubmissionErrorsSerializer::fromArray(...),
        );
    }
}
