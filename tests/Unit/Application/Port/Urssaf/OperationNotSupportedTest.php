<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Port\Urssaf;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;

final class OperationNotSupportedTest extends TestCase
{
    public function testNewsFeedOnTestApi(): void
    {
        $exception = OperationNotSupported::newsFeedOnTestApi();

        static::assertSame('The news feed (getPlatformNewsFeed) is not available on the test API.', $exception->getMessage());
        static::assertSame(LogicException::class, get_parent_class($exception));
        static::assertContains(UrssafException::class, class_implements($exception));
    }
}
