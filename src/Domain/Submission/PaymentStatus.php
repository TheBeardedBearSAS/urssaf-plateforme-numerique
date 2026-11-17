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
 * Payment status of a declaration ("paymentStatus" of the API), in the order of the contract.
 */
enum PaymentStatus: string
{
    case NotInitiated = 'NOT_INITIATED';
    case Processing = 'PROCESSING';
    case Interlocution = 'INTERLOCUTION';
    case Declared = 'DECLARED';
    case Processed = 'PROCESSED';

    /**
     * Null for a missing or unknown code: an unexpected status never breaks a read.
     */
    public static function fromCode(?string $code): ?self
    {
        return $code === null ? null : self::tryFrom($code);
    }
}
