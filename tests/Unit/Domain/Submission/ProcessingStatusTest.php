<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingStatus;

final class ProcessingStatusTest extends TestCase
{
    public function testKnownStatus(): void
    {
        $status = ProcessingStatus::of('ACCEPTED', 'Accepted');

        static::assertSame('ACCEPTED', $status->raw());
        static::assertSame('Accepted', $status->label());
        static::assertSame(FileStatus::Accepted, $status->status());
    }

    public function testUnknownStatusKeepsRawValue(): void
    {
        $status = ProcessingStatus::of('SOMETHING_NEW', null);

        static::assertSame('SOMETHING_NEW', $status->raw());
        static::assertNull($status->label());
        static::assertNull($status->status());
    }

    public function testMissingStatus(): void
    {
        $status = ProcessingStatus::of(null, null);

        static::assertNull($status->raw());
        static::assertNull($status->status());
    }
}
