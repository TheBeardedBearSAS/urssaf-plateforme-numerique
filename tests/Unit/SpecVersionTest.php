<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\SpecVersion;

final class SpecVersionTest extends TestCase
{
    public function testItExposesTheImplementedSpecificationVersion(): void
    {
        static::assertMatchesRegularExpression('/^1\.0\.0$/', SpecVersion::CURRENT);
    }
}
