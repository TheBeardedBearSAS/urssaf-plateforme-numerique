<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\UploadResponseMapper;

/**
 * Reads the reference of a 2xx upload answer. An answer without a readable `urssafRef` leaves the outcome unknown:
 * URSSAF received the file (ADR 0003).
 *
 * @internal
 */
final class UploadReferenceReader
{
    /**
     * @throws OutcomeUnknown when `urssafRef` is missing, empty or invalid
     */
    public static function read(Operation $operation, ApiResponse $response): UrssafReference
    {
        return self::tryRead($response)
            ?? throw OutcomeUnknown::unreadableAnswer($operation, 'field "urssafRef" is missing, empty or invalid');
    }

    private static function tryRead(ApiResponse $response): ?UrssafReference
    {
        try {
            return UploadResponseMapper::urssafReference($response->payload());
        } catch (UnexpectedUrssafResponse) {
            return null;
        }
    }
}
