<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;

/**
 * An upload may have reached URSSAF but its answer was lost or unreadable (ADR 0003). Never resent automatically: the
 * submission is reconciled by searching URSSAF. Not retryable. The message never contains the transport message (it
 * embeds the URI): only the class name of the cause is kept.
 */
final class OutcomeUnknown extends RuntimeException implements UrssafException
{
    private function __construct(string $message, private readonly Operation $operation)
    {
        parent::__construct($message);
    }

    public static function afterTransportError(Operation $operation, string $causeClass): self
    {
        return new self(
            \sprintf('URSSAF operation %s failed with %s: the upload may have been received.', $operation->value, $causeClass),
            $operation,
        );
    }

    public static function afterStatus(Operation $operation, int $httpStatus): self
    {
        return new self(
            \sprintf('URSSAF operation %s answered HTTP %d: the upload may have been received.', $operation->value, $httpStatus),
            $operation,
        );
    }

    public static function unreadableAnswer(Operation $operation, string $reason): self
    {
        return new self(
            \sprintf('URSSAF operation %s answered an unreadable response (%s): the upload may have been received.', $operation->value, $reason),
            $operation,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function mayHaveReachedUrssaf(): bool
    {
        return true;
    }
}
