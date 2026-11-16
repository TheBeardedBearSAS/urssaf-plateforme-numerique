<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;

/**
 * URSSAF refused the whole call (HTTP 400). Nothing was recorded by URSSAF; not retryable as is. The error codes are
 * kept verbatim in {@see self::errors()}.
 */
final class UrssafRequestRejected extends RuntimeException implements UrssafException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly int $httpStatus,
        private readonly ApiErrors $errors,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function withErrors(Operation $operation, int $httpStatus, ApiErrors $errors): self
    {
        return new self(
            \sprintf(
                'URSSAF operation %s rejected the request (HTTP %d): %s.',
                $operation->value,
                $httpStatus,
                $errors->isEmpty() ? 'no error code' : implode(', ', $errors->codes()),
            ),
            $operation,
            $httpStatus,
            $errors,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }

    public function hasErrorCode(string $code): bool
    {
        return $this->errors->hasCode($code);
    }
}
