<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;

final class EnumsTest extends TestCase
{
    public function testFileStatus(): void
    {
        static::assertSame(['IN_PROCESS', 'ACCEPTED', 'REJECTED'], array_column(FileStatus::cases(), 'value'));
        static::assertSame(FileStatus::InProcess, FileStatus::fromCode('IN_PROCESS'));
        static::assertSame(FileStatus::Accepted, FileStatus::fromCode('ACCEPTED'));
        static::assertSame(FileStatus::Rejected, FileStatus::fromCode('REJECTED'));
        static::assertNull(FileStatus::fromCode(null));
        static::assertNull(FileStatus::fromCode('UNKNOWN'));
        static::assertNull(FileStatus::fromCode('accepted'));
        static::assertFalse(FileStatus::InProcess->isFinal());
        static::assertTrue(FileStatus::Accepted->isFinal());
        static::assertTrue(FileStatus::Rejected->isFinal());
    }

    public function testPaymentStatus(): void
    {
        static::assertSame(
            ['NOT_INITIATED', 'PROCESSING', 'INTERLOCUTION', 'DECLARED', 'PROCESSED'],
            array_column(PaymentStatus::cases(), 'value'),
        );
        static::assertSame(PaymentStatus::NotInitiated, PaymentStatus::fromCode('NOT_INITIATED'));
        static::assertSame(PaymentStatus::Declared, PaymentStatus::fromCode('DECLARED'));
        static::assertSame(PaymentStatus::Processing, PaymentStatus::fromCode('PROCESSING'));
        static::assertSame(PaymentStatus::Interlocution, PaymentStatus::fromCode('INTERLOCUTION'));
        static::assertSame(PaymentStatus::Processed, PaymentStatus::fromCode('PROCESSED'));
        static::assertNull(PaymentStatus::fromCode(null));
        static::assertNull(PaymentStatus::fromCode('UNKNOWN'));
    }

    public function testDeclarationType(): void
    {
        static::assertSame(['DECLARATION', 'REGULARISATION'], array_column(DeclarationType::cases(), 'value'));
        static::assertSame(DeclarationType::Declaration, DeclarationType::fromCode('DECLARATION'));
        static::assertSame(DeclarationType::Regularisation, DeclarationType::fromCode('REGULARISATION'));
        static::assertNull(DeclarationType::fromCode(null));
        static::assertNull(DeclarationType::fromCode('UNKNOWN'));
    }

    public function testSubmissionKind(): void
    {
        static::assertSame(['identification', 'declaration'], array_column(SubmissionKind::cases(), 'value'));
        static::assertSame(SubmissionKind::Identification, SubmissionKind::from('identification'));
        static::assertSame(SubmissionKind::Declaration, SubmissionKind::from('declaration'));
    }
}
