<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use LogicException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\PaymentStatusChanged;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;

/**
 * Domain events produced by the aggregate itself (never built by hand), at Harness::NOW, to test what is derived from
 * them: one factory per event, for the declaration "sub-1" or the identification "sub-1".
 *
 * @mago-expect lint:too-many-methods
 */
final class DomainEventSamples
{
    private function __construct() {}

    public static function uploaded(SubmissionKind $kind = SubmissionKind::Declaration): DomainEvent
    {
        $submission = self::started($kind);
        $submission->markUploaded(SubmissionSamples::urssafReference(1), Harness::at(Harness::NOW));

        return self::only($submission);
    }

    /**
     * @param list<SubmissionError> $errors
     */
    public static function uploadRejected(array $errors, SubmissionKind $kind = SubmissionKind::Declaration): DomainEvent
    {
        $submission = self::started($kind);
        $submission->markUploadRejected($errors, Harness::at(Harness::NOW));

        return self::only($submission);
    }

    public static function uploadFailed(): DomainEvent
    {
        $submission = self::started(SubmissionKind::Declaration);
        $submission->markFailed(Harness::at(Harness::NOW));

        return self::only($submission);
    }

    public static function outcomeUnknown(SubmissionKind $kind = SubmissionKind::Declaration): DomainEvent
    {
        $submission = self::started($kind);
        $submission->markOutcomeUnknown(Harness::at(Harness::NOW));

        return self::only($submission);
    }

    public static function notReceived(SubmissionKind $kind = SubmissionKind::Declaration): DomainEvent
    {
        $submission = self::started($kind);
        $submission->markOutcomeUnknown(Harness::at(Harness::NOW));
        $submission->releaseEvents();
        $submission->markNotReceived(Harness::at('2026-01-16T08:30:00Z'));

        return self::only($submission);
    }

    public static function accepted(ReportFiles $reports, SubmissionKind $kind = SubmissionKind::Declaration): DomainEvent
    {
        return self::processed('ACCEPTED', $reports, $kind);
    }

    public static function rejected(ReportFiles $reports, SubmissionKind $kind = SubmissionKind::Declaration): DomainEvent
    {
        return self::processed('REJECTED', $reports, $kind);
    }

    public static function paymentStatusChanged(?string $previous, string $current): DomainEvent
    {
        $submission = self::started(SubmissionKind::Declaration);
        $submission->markUploaded(SubmissionSamples::urssafReference(1), Harness::at(Harness::NOW));
        if ($previous !== null) {
            $submission->applySnapshot(SubmissionSamples::snapshot('IN_PROCESS', $previous), Harness::at(Harness::NOW));
        }
        $submission->releaseEvents();
        $submission->applySnapshot(SubmissionSamples::snapshot('IN_PROCESS', $current), Harness::at('2026-01-16T09:00:00Z'));

        return self::only($submission);
    }

    /**
     * Not reachable through the aggregate (only a declaration has a payment status): built by hand.
     */
    public static function paymentStatusChangedOfAnIdentification(): DomainEvent
    {
        return new PaymentStatusChanged(
            SubmissionSamples::identity(1, SubmissionKind::Identification, null),
            SubmissionSamples::urssafReference(1),
            null,
            'DECLARED',
            Harness::at(Harness::NOW),
        );
    }

    public static function reports(): ReportFiles
    {
        return ReportFiles::of(FileReference::of('file-global', 'global.pdf'), null, FileReference::of('file-anomalies', null), null);
    }

    private static function processed(string $status, ReportFiles $reports, SubmissionKind $kind): DomainEvent
    {
        $submission = self::started($kind);
        $submission->markUploaded(SubmissionSamples::urssafReference(1), Harness::at(Harness::NOW));
        $submission->releaseEvents();
        $submission->applySnapshot(
            new SubmissionSnapshot($status, null, [], $reports, [], null),
            Harness::at('2026-01-16T09:00:00Z'),
        );

        return self::only($submission);
    }

    private static function started(SubmissionKind $kind): Submission
    {
        return Submission::start(
            $kind === SubmissionKind::Declaration
                ? SubmissionSamples::identity(1, SubmissionKind::Declaration, DeclarationType::Regularisation)
                : SubmissionSamples::identity(1, SubmissionKind::Identification, null),
            Harness::at(Harness::NOW),
        );
    }

    private static function only(Submission $submission): DomainEvent
    {
        $events = $submission->releaseEvents();

        return \count($events) === 1 ? $events[0] : throw new LogicException('Exactly one event was expected.');
    }
}
