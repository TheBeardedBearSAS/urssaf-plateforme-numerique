<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf;

use LogicException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;

/**
 * The operation is not available on the configured target.
 */
final class OperationNotSupported extends LogicException implements UrssafException
{
    public static function newsFeedOnTestApi(): self
    {
        return new self('The news feed (getPlatformNewsFeed) is not available on the test API.');
    }
}
