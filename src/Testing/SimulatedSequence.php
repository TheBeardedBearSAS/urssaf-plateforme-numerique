<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Testing;

use UnitEnum;

/**
 * Scripted statuses of a simulated submission: each step is returned once, in order, the last one is repeated.
 *
 * @template-covariant T of UnitEnum
 *
 * @internal
 */
final class SimulatedSequence
{
    private int $position = 0;

    /**
     * @param non-empty-list<T> $steps
     */
    public function __construct(
        private readonly array $steps,
    ) {}

    /**
     * The step the next {@see next()} returns, without moving.
     *
     * @return T
     */
    public function head(): UnitEnum
    {
        return $this->steps[$this->position];
    }

    /**
     * Returns the current step and moves to the following one (the last one stays).
     *
     * @return T
     */
    public function next(): UnitEnum
    {
        $step = $this->head();
        if ($this->position < \count($this->steps) - 1) {
            ++$this->position;
        }

        return $step;
    }
}
