<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;

/**
 * Transient failure: network error, timeout, HTTP 429, 5xx. Retryable.
 *
 * {@see self::mayHaveReachedUrssaf()} tells whether URSSAF may have processed the call anyway: true for transport
 * errors and HTTP 5xx answers of the API call; false for 4xx answers and for every failure of the token request (the
 * API call itself was never sent). Uploads that may have reached URSSAF are reported as {@see OutcomeUnknown} instead
 * (ADR 0003). The transport cause is kept as a class name only, never its message (ADR 0002).
 */
final class UrssafUnavailable extends RuntimeException implements RetryableException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly ?int $httpStatus,
        private readonly ?int $retryAfterSeconds,
        private readonly bool $mayHaveReachedUrssaf,
        private readonly ?string $causeClass,
        private readonly ApiErrors $errors,
    ) {
        parent::__construct($message, $httpStatus ?? 0);
    }

    /**
     * The API call answered a transient HTTP status (429, 5xx).
     *
     * @param int|null $retryAfterSeconds value of the Retry-After header when provided
     */
    public static function withStatus(
        Operation $operation,
        int $httpStatus,
        ?int $retryAfterSeconds,
        ApiErrors $errors,
    ): self {
        return new self(
            \sprintf('URSSAF operation %s is temporarily unavailable (HTTP %d).', $operation->value, $httpStatus),
            $operation,
            $httpStatus,
            $retryAfterSeconds,
            $httpStatus >= 500,
            null,
            $errors,
        );
    }

    /**
     * The API call failed without an HTTP answer (e.g. connection error, timeout).
     *
     * @param string $causeClass class name of the transport exception (its message is never kept)
     */
    public static function dueToTransportError(Operation $operation, string $causeClass): self
    {
        return new self(
            \sprintf('URSSAF operation %s could not be reached (%s).', $operation->value, $causeClass),
            $operation,
            null,
            null,
            true,
            $causeClass,
            ApiErrors::none(),
        );
    }

    /**
     * The access token needed by the call could not be obtained (transient HTTP status, or transport error when
     * $httpStatus is null): the API call itself was not sent.
     */
    public static function whileAuthenticating(
        Operation $operation,
        ?int $httpStatus,
        ?int $retryAfterSeconds,
        ?string $causeClass,
        ApiErrors $errors,
    ): self {
        return new self(
            \sprintf('URSSAF authentication for operation %s is temporarily unavailable.', $operation->value),
            $operation,
            $httpStatus,
            $retryAfterSeconds,
            false,
            $causeClass,
            $errors,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    /**
     * Null for transport errors (no HTTP response).
     */
    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function isQuotaExceeded(): bool
    {
        return $this->httpStatus === 429;
    }

    /**
     * True when URSSAF may have processed the call despite the failure (see the class documentation).
     */
    public function mayHaveReachedUrssaf(): bool
    {
        return $this->mayHaveReachedUrssaf;
    }

    /**
     * Class name of the transport exception, when there was one.
     */
    public function causeClass(): ?string
    {
        return $this->causeClass;
    }

    /**
     * The errors parsed from the answer body, when it had a readable one.
     */
    public function errors(): ApiErrors
    {
        return $this->errors;
    }
}
