<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

/**
 * Processing status of an uploaded file ("status" of the API).
 */
enum FileStatus: string
{
    case InProcess = 'IN_PROCESS';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';

    /**
     * Null for a missing or unknown code: an unexpected status never breaks a read.
     */
    public static function fromCode(?string $code): ?self
    {
        return $code === null ? null : self::tryFrom($code);
    }

    /**
     * True once URSSAF has finished processing the file (accepted or rejected).
     */
    public function isFinal(): bool
    {
        return $this !== self::InProcess;
    }
}
