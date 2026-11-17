<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;

/**
 * Explicit JSON shape of the errors of a refused upload: the "code" and "message" of URSSAF, each optional. The
 * "description" is not part of the domain value and is never stored (ADR 0009).
 *
 * @phpstan-type ErrorData array{code: string|null, message: string|null}
 */
final class SubmissionErrorsSerializer
{
    /**
     * @param list<SubmissionError> $errors
     *
     * @return list<ErrorData>
     */
    public static function toArray(array $errors): array
    {
        return array_map(
            static fn(SubmissionError $error): array => ['code' => $error->code(), 'message' => $error->message()],
            $errors,
        );
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape list<ErrorData>
     *
     * @return list<SubmissionError>
     */
    public static function fromArray(array $data): array
    {
        /** @var list<ErrorData> $data */
        return array_map(
            static fn(array $error): SubmissionError => SubmissionError::of($error['code'], $error['message']),
            $data,
        );
    }
}
