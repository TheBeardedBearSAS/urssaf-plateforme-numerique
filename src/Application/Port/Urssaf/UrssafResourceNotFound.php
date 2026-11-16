<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;

/**
 * URSSAF answered 404: the submission or the file does not exist. Not retryable.
 */
final class UrssafResourceNotFound extends RuntimeException implements UrssafException
{
    private function __construct(string $message, private readonly Operation $operation, private readonly ApiErrors $errors)
    {
        parent::__construct($message, 404);
    }

    public static function withErrors(Operation $operation, ApiErrors $errors): self
    {
        return new self(
            \sprintf('URSSAF operation %s found no such resource (HTTP 404).', $operation->value),
            $operation,
            $errors,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }
}
