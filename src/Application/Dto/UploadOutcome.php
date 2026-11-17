<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

/**
 * What an upload command did (ADR 0004).
 */
enum UploadOutcome: string
{
    /** URSSAF accepted the file and returned its reference: the submission is in process. */
    case Uploaded = 'uploaded';

    /** The same file was already uploaded under this external reference: the record is returned, nothing was sent. */
    case Replayed = 'replayed';

    /** Another process is sending the same file (or reclaimed the record first): nothing was sent. */
    case NotClaimed = 'not_claimed';

    /** URSSAF refused the file (HTTP 400, final): the errors are in the report. */
    case Rejected = 'rejected';

    /** URSSAF refused the tenant credentials. */
    case InvalidCredentials = 'invalid_credentials';

    /** The call may have reached URSSAF: to be reconciled, never sent again automatically (ADR 0003). */
    case OutcomeUnknown = 'outcome_unknown';

    /** The submission was already uploaded the maximum number of times: it is not uploaded again automatically. */
    case Exhausted = 'exhausted';
}
