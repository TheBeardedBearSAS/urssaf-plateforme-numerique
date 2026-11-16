<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use LogicException;
use Throwable;

/**
 * Responses scripted for one operation of a fake: each call takes the next one, which is returned, or thrown when it is a
 * throwable.
 *
 * @template T
 */
final class ResponseQueue
{
    /** @var list<T|Throwable> */
    private array $responses = [];

    /**
     * @param T|Throwable ...$responses
     */
    public function push(mixed ...$responses): void
    {
        $this->responses = [...$this->responses, ...array_values($responses)];
    }

    /**
     * @return T
     *
     * @throws LogicException when nothing is scripted
     */
    public function next(string $operation): mixed
    {
        $response = array_shift($this->responses) ?? throw new LogicException(\sprintf('Unexpected %s() call.', $operation));

        return $response instanceof Throwable ? throw $response : $response;
    }
}
