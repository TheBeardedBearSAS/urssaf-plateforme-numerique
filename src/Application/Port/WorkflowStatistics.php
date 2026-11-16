<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

/**
 * Counts the records by tenant and state, for monitoring. A port of its own (not a repository method), so that a custom
 * persistence can do without it.
 */
interface WorkflowStatistics
{
    /**
     * @return list<StateCount> the submissions by tenant and state (pairs without record are omitted)
     */
    public function submissionStates(): array;
}
