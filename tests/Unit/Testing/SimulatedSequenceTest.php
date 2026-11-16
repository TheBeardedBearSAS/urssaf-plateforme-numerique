<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedSequence;

final class SimulatedSequenceTest extends TestCase
{
    public function testEachStepIsReturnedOnceThenTheLastOneIsRepeated(): void
    {
        $sequence = new SimulatedSequence([FileStatus::InProcess, FileStatus::Rejected, FileStatus::Accepted]);

        $steps = [$sequence->next(), $sequence->next(), $sequence->next(), $sequence->next()];

        static::assertSame([FileStatus::InProcess, FileStatus::Rejected, FileStatus::Accepted, FileStatus::Accepted], $steps);
    }

    public function testASingleStepIsRepeated(): void
    {
        $sequence = new SimulatedSequence([FileStatus::Accepted]);

        static::assertSame([FileStatus::Accepted, FileStatus::Accepted], [$sequence->next(), $sequence->next()]);
    }

    public function testTheHeadIsTheStepTheNextReadReturnsWithoutMoving(): void
    {
        $sequence = new SimulatedSequence([FileStatus::InProcess, FileStatus::Accepted]);

        static::assertSame(FileStatus::InProcess, $sequence->head());
        static::assertSame(FileStatus::InProcess, $sequence->head());
        $sequence->next();
        static::assertSame(FileStatus::Accepted, $sequence->head());
    }
}
