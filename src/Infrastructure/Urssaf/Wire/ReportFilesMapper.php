<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Reads the report file references of a submission (wire keys verbatim). A declaration has no
 * `affiliatedUsersFileRef`: the key is simply absent.
 */
final class ReportFilesMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public static function map(Payload $submission): ReportFiles
    {
        return ReportFiles::of(
            FileReferenceMapper::field($submission, 'globalReportRef'),
            FileReferenceMapper::field($submission, 'detailedReportRef'),
            FileReferenceMapper::field($submission, 'usersAnomaliesReportRef'),
            FileReferenceMapper::field($submission, 'affiliatedUsersFileRef'),
        );
    }
}
