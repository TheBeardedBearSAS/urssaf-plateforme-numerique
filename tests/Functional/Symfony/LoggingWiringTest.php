<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcher;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\ThrowingListener;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

final class LoggingWiringTest extends TestCase
{
    public function testTheFailureReporterIsThePsrLoggerOne(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(PsrLoggerFailureReporter::class, $harness->privateService(FailureReporter::class, FailureReporter::class));
    }

    public function testTheDomainEventsArePublishedOnTheEventDispatcher(): void
    {
        $harness = Harness::boot();
        $published = [];
        $harness->privateService('event_dispatcher', SymfonyEventDispatcher::class)->addListener(
            SubmissionUploaded::class,
            static function (SubmissionUploaded $event) use (&$published): void {
                $published[] = $event;
            },
        );

        Scenario::uploadedIdentification($harness);

        static::assertCount(1, $published);
        static::assertInstanceOf(EventDispatcherInterface::class, $harness->privateService('event_dispatcher', EventDispatcherInterface::class));
    }

    public function testAnEventPublicationFailureAfterCommitIsLoggedAndTheStateIsKept(): void
    {
        $harness = Harness::boot();
        $harness->privateService('event_dispatcher', SymfonyEventDispatcher::class)->addListener(SubmissionUploaded::class, new ThrowingListener());

        $report = Scenario::uploadedIdentification($harness);

        static::assertSame(UploadOutcome::Uploaded, $report->outcome);
        $records = $harness->privateService('logger', CapturingLogger::class)->records();
        $errors = array_values(array_filter($records, static fn(array $record): bool => $record['level'] === 'error'));
        static::assertCount(1, $errors);
        static::assertSame('Domain event publication failed after commit.', $errors[0]['message']);
        static::assertSame(
            ['exception' => LogicException::class, 'message' => 'listener exploded', 'events' => [SubmissionUploaded::class]],
            $errors[0]['context'],
        );
    }

    public function testTheGatewayLogsThroughThePackageLogger(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->failTransport(HttpScript::ROUTE_UPLOAD_IDENTIFICATION);

        $report = Scenario::uploadedIdentification($harness);

        static::assertSame(UploadOutcome::OutcomeUnknown, $report->outcome);
        $messages = array_map(static fn(array $record): string => $record['message'], $harness->privateService('logger', CapturingLogger::class)->records());
        static::assertNotSame([], $messages);
        foreach ($messages as $message) {
            static::assertStringNotContainsString('fictitious-access-token', $message);
        }
    }
}
