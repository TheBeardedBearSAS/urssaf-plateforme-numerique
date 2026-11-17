<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;

/**
 * What the commands print of a local submission: identity, state and URSSAF tracking. The error codes of a refused
 * upload are printed, never their descriptions (ADR 0009, ADR 0012).
 */
final class CommandViews
{
    /**
     * @return array<string, string|int|null> the fields of the JSON output, keyed by wire-style name
     */
    public static function data(SubmissionView $view): array
    {
        return [
            'submission_id' => $view->submissionId,
            'tenant_id' => $view->tenantId,
            'platform_id' => $view->platformId,
            'external_reference' => $view->externalReference,
            'kind' => $view->kind,
            'declaration_type' => $view->declarationType,
            'file_name' => $view->fileName,
            'state' => $view->state,
            'attempts' => $view->attempts,
            'urssaf_ref' => $view->urssaf->urssafRef,
            'status' => $view->urssaf->rawStatus,
            'payment_status' => $view->urssaf->rawPaymentStatus,
            'last_polled_at' => $view->urssaf->lastPolledAt,
            'created_at' => $view->timestamps->createdAt,
            'updated_at' => $view->timestamps->updatedAt,
            'error_codes' => implode(', ', array_map(static fn(array $error): string => $error['code'] ?? '-', $view->errors)),
        ];
    }

    /**
     * @return list<array{string, string}> field and value, "-" when unknown
     */
    public static function rows(SubmissionView $view): array
    {
        $rows = [];
        foreach (self::data($view) as $field => $value) {
            $rows[] = [$field, $value === null || $value === '' ? '-' : (string) $value];
        }

        return $rows;
    }
}
