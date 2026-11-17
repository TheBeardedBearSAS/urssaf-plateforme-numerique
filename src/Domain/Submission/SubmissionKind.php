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
 * Kind of file a submission uploads (package value, not a wire value).
 */
enum SubmissionKind: string
{
    case Identification = 'identification';
    case Declaration = 'declaration';
}
