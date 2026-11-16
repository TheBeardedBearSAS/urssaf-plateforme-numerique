<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support;

use Closure;
use Doctrine\DBAL\Driver\Middleware as DriverMiddleware;
use Doctrine\DBAL\Logging\Middleware;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * Runs an action once, right before the next savepoint of a connection is created (plug it through
 * {@see self::middleware()}): what another process commits after a uniqueness check of the repository and before its
 * insert (which runs in a savepoint inside the transaction of the use case). The action runs in the enclosing
 * transaction, so the rollback of the savepoint does not undo it.
 */
final class BeforeNextSavepoint implements LoggerInterface
{
    use LoggerTrait;

    /** @var (Closure(): void)|null */
    private ?Closure $action = null;

    public function middleware(): DriverMiddleware
    {
        return new Middleware($this);
    }

    /**
     * @param Closure(): void $action
     * @param-immediately-invoked-callable $action
     */
    public function arm(Closure $action): void
    {
        $this->action = $action;
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        // @mago-expect analysis:mixed-assignment The PSR-3 context is untyped; it is narrowed right below.
        $sql = $context['sql'] ?? null;
        $action = $this->action;
        if (!$action instanceof Closure || !\is_string($sql) || !str_starts_with($sql, 'SAVEPOINT')) {
            return;
        }
        $this->action = null;
        $action();
    }
}
