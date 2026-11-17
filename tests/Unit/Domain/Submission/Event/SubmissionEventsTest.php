<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission\Event;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\PaymentStatusChanged;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionAccepted;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionNotReceived;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionOutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploadFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploadRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission\SubmissionFixtures;

final class SubmissionEventsTest extends TestCase
{
    public function testUploadEvents(): void
    {
        $identity = SubmissionFixtures::declaration();
        $reference = SubmissionFixtures::reference();
        $at = SubmissionFixtures::now();
        $errors = [SubmissionError::of('ERR_CODE', 'A message.')];

        $uploaded = new SubmissionUploaded($identity, $reference, $at);
        static::assertSame($identity, $uploaded->identity());
        static::assertSame($reference, $uploaded->urssafReference());
        static::assertSame(SubmissionFixtures::NOW, $uploaded->occurredAt()->format('Y-m-d\TH:i:s\Z'));

        $rejected = new SubmissionUploadRejected($identity, $errors, $at);
        static::assertSame($identity, $rejected->identity());
        static::assertSame($errors, $rejected->errors());
        static::assertSame(SubmissionFixtures::NOW, $rejected->occurredAt()->format('Y-m-d\TH:i:s\Z'));

        $failed = new SubmissionUploadFailed($identity, SubmissionState::InvalidCredentials, $at);
        static::assertSame($identity, $failed->identity());
        static::assertSame(SubmissionState::InvalidCredentials, $failed->state());
        static::assertSame(SubmissionFixtures::NOW, $failed->occurredAt()->format('Y-m-d\TH:i:s\Z'));

        $unknown = new SubmissionOutcomeUnknown($identity, $at);
        static::assertSame($identity, $unknown->identity());
        static::assertSame(SubmissionFixtures::NOW, $unknown->occurredAt()->format('Y-m-d\TH:i:s\Z'));

        $notReceived = new SubmissionNotReceived($identity, $at);
        static::assertSame($identity, $notReceived->identity());
        static::assertSame(SubmissionFixtures::NOW, $notReceived->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testPollEvents(): void
    {
        $identity = SubmissionFixtures::declaration();
        $reference = SubmissionFixtures::reference();
        $snapshot = SubmissionFixtures::snapshot('ACCEPTED', 'PROCESSED');
        $at = SubmissionFixtures::now();

        $accepted = new SubmissionAccepted($identity, $reference, $snapshot, $at);
        static::assertSame($identity, $accepted->identity());
        static::assertSame($reference, $accepted->urssafReference());
        static::assertSame($snapshot, $accepted->snapshot());
        static::assertSame(SubmissionFixtures::NOW, $accepted->occurredAt()->format('Y-m-d\TH:i:s\Z'));

        $rejected = new SubmissionRejected($identity, $reference, $snapshot, $at);
        static::assertSame($identity, $rejected->identity());
        static::assertSame($reference, $rejected->urssafReference());
        static::assertSame($snapshot, $rejected->snapshot());
        static::assertSame(SubmissionFixtures::NOW, $rejected->occurredAt()->format('Y-m-d\TH:i:s\Z'));

        $changed = new PaymentStatusChanged($identity, $reference, null, 'DECLARED', $at);
        static::assertSame($identity, $changed->identity());
        static::assertSame($reference, $changed->urssafReference());
        static::assertNull($changed->previousRawPaymentStatus());
        static::assertSame('DECLARED', $changed->rawPaymentStatus());
        static::assertSame(SubmissionFixtures::NOW, $changed->occurredAt()->format('Y-m-d\TH:i:s\Z'));
        static::assertSame('PROCESSING', (new PaymentStatusChanged($identity, $reference, 'PROCESSING', null, $at))->previousRawPaymentStatus());
    }
}
