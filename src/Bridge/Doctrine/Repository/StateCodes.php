<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Repository;

use Closure;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * The stored codes of the submission states a DQL condition selects, derived from the domain enum so that a query cannot
 * drift from the predicates of {@see SubmissionState}.
 */
final class StateCodes
{
    /**
     * @param Closure(SubmissionState): bool $predicate
     * @param-immediately-invoked-callable $predicate
     *
     * @return list<string> in the order of the enum cases
     */
    public static function where(Closure $predicate): array
    {
        $codes = [];
        foreach (SubmissionState::cases() as $state) {
            if (!$predicate($state)) {
                continue;
            }
            $codes[] = $state->value;
        }

        return $codes;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::where(static fn(): bool => true);
    }
}
