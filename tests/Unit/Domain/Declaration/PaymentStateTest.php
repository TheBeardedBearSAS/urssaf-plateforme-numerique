<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\PaymentState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;

final class PaymentStateTest extends TestCase
{
    public function testKnownStatus(): void
    {
        $state = PaymentState::of('PROCESSED', 'Processed');

        static::assertSame('PROCESSED', $state->raw());
        static::assertSame('Processed', $state->label());
        static::assertSame(PaymentStatus::Processed, $state->status());
    }

    public function testUnknownStatusKeepsRawValue(): void
    {
        $state = PaymentState::of('SOMETHING_NEW', null);

        static::assertSame('SOMETHING_NEW', $state->raw());
        static::assertNull($state->label());
        static::assertNull($state->status());
    }

    public function testMissingStatus(): void
    {
        $state = PaymentState::of(null, null);

        static::assertNull($state->raw());
        static::assertNull($state->status());
    }
}
