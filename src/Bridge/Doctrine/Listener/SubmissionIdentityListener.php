<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Listener;

use ReflectionProperty;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;

/**
 * Entity listener of {@see Submission} (declared in its XML mapping): completes the embedded {@see SubmissionIdentity}
 * once a row is loaded.
 *
 * The submission id is held twice by the aggregate: as its Doctrine identifier and inside its identity. One column
 * cannot be mapped by two fields, so the embeddable maps every identity field but the id, which this listener copies
 * from the identifier, as the ORM does for any mapped field (the property is readonly and still uninitialized right
 * after the hydration of the embeddable).
 */
final class SubmissionIdentityListener
{
    public function postLoad(Submission $submission): void
    {
        (new ReflectionProperty(SubmissionIdentity::class, 'id'))->setValue($submission->identity(), $submission->id());
    }
}
