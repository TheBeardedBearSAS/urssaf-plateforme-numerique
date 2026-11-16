<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Serializer\SubmissionSnapshotSerializer;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;

/**
 * JSON column type of an optional {@see SubmissionSnapshot} (what the last poll read): a submission never polled stores
 * SQL NULL. No query filters on it (ADR 0011): the raw statuses are also real columns. A stored document is never
 * quoted in a conversion error (ADR 0009).
 */
final class SubmissionSnapshotType extends Type
{
    public const NAME = 'urssaf_pn_submission_snapshot';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof SubmissionSnapshot) {
            throw InvalidType::new($value, self::class, [SubmissionSnapshot::class, 'null']);
        }

        return JsonConversion::encode(SubmissionSnapshotSerializer::toArray($value));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SubmissionSnapshot
    {
        return JsonConversion::decode(
            $value,
            self::class,
            SubmissionSnapshot::class,
            SubmissionSnapshotSerializer::fromArray(...),
        );
    }
}
