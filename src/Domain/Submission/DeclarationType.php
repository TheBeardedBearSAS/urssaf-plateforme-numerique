<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

/**
 * Type of a declaration file ("type" of the API).
 */
enum DeclarationType: string
{
    case Declaration = 'DECLARATION';
    case Regularisation = 'REGULARISATION';

    /**
     * Null for a missing or unknown code: an unexpected type never breaks a read.
     */
    public static function fromCode(?string $code): ?self
    {
        return $code === null ? null : self::tryFrom($code);
    }
}
