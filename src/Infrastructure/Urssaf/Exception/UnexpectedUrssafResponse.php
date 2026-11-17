<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception;

use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;
use Throwable;

/**
 * URSSAF answered with something the contract does not describe (unknown HTTP status, invalid JSON,
 * wrongly typed field, unparseable value). Not retryable: the response must be investigated.
 */
final class UnexpectedUrssafResponse extends RuntimeException implements UrssafException
{
    public static function invalidPayload(string $reason): self
    {
        return new self(\sprintf('Unexpected URSSAF response: %s.', $reason));
    }

    public static function causedBy(string $reason, Throwable $previous): self
    {
        return new self(\sprintf('Unexpected URSSAF response: %s.', $reason), 0, $previous);
    }

    public static function unexpectedStatus(Operation $operation, int $httpStatus): self
    {
        return new self(
            \sprintf('Unexpected URSSAF response for operation %s: HTTP %d.', $operation->value, $httpStatus),
            $httpStatus,
        );
    }
}
