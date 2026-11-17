<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Exception;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\DomainException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;

/**
 * Thrown when a tenant uploads a file under an external reference that is already recorded with another content (other
 * fingerprint, ADR 0004): the host must use another reference.
 *
 * Violation code: "submission.already_recorded".
 */
final class SubmissionAlreadyRecorded extends DomainException
{
    public static function withExternalReference(ExternalReference $externalReference, SubmissionId $existing): self
    {
        return new self(
            \sprintf('The external reference "%s" is already recorded with another file.', $externalReference->value()),
            'submission.already_recorded',
            ['external_reference' => $externalReference->value(), 'submission_id' => $existing->value()],
        );
    }
}
