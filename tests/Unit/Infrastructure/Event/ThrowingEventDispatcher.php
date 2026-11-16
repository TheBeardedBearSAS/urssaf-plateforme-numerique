<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * PSR-14 dispatcher fake whose listeners always fail.
 */
final readonly class ThrowingEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private Throwable $failure) {}

    public function dispatch(object $event): never
    {
        throw $this->failure;
    }
}
