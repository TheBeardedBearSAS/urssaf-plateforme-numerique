<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Outbox;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\RecordingMessageBus;

final class MessengerIntegrationEventSinkTest extends TestCase
{
    public function testTheEventIsDispatchedOnTheBusAsIs(): void
    {
        $bus = new RecordingMessageBus();
        $event = new SubmissionUploaded('event-1', '2025-06-02T08:00:00Z', 'tenant-1', 'submission-1', 'invoice-1', 'declaration', 'DECLARATION', 'DPDE_202505_24001_000_20250428150823.xml.gz', 'unique-file-submission-id-12345');

        (new MessengerIntegrationEventSink($bus))->dispatch($event);

        static::assertSame([$event], $bus->messages());
    }
}
