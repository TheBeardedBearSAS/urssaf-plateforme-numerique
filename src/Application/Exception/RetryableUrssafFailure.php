<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Exception;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;

/**
 * Thrown by the handlers after a temporary URSSAF failure (unavailable, quota exceeded, local rate limit, token failure):
 * the affected submission was moved to "error" (an upload) or left as it was (a poll) and the message must be retried later.
 * The original failure (with its retry-after delay when known) is the previous exception.
 */
final class RetryableUrssafFailure extends RuntimeException implements RetryableException
{
    public static function causedBy(RetryableException $previous): self
    {
        return new self('URSSAF is temporarily unavailable: the submission must be retried later.', 0, $previous);
    }
}
