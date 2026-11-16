<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

/**
 * Kind of file a submission uploads (package value, not a wire value).
 */
enum SubmissionKind: string
{
    case Identification = 'identification';
    case Declaration = 'declaration';
}
