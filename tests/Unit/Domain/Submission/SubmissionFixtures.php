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

use LogicException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ContentFingerprint;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Fictitious domain objects shared by the submission aggregate tests.
 */
final class SubmissionFixtures
{
    public const NOW = '2026-01-15T10:00:00Z';
    public const FINGERPRINT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    public const URSSAF_REF = 'urssaf-ref-1';

    public static function at(string $iso8601): Instant
    {
        return Instant::fromIso8601($iso8601);
    }

    public static function now(): Instant
    {
        return self::at(self::NOW);
    }

    public static function identity(SubmissionKind $kind, ?DeclarationType $type): SubmissionIdentity
    {
        return new SubmissionIdentity(
            SubmissionId::fromString('submission-1'),
            TenantId::fromString('tenant-1'),
            PlatformId::fromString('platform-1'),
            ExternalReference::fromString('host-ref-1'),
            $kind,
            $type,
            FileName::fromString('file-1.zip'),
            ContentFingerprint::fromHex(self::FINGERPRINT),
        );
    }

    public static function declaration(): SubmissionIdentity
    {
        return self::identity(SubmissionKind::Declaration, DeclarationType::Declaration);
    }

    public static function identification(): SubmissionIdentity
    {
        return self::identity(SubmissionKind::Identification, null);
    }

    public static function reference(): UrssafReference
    {
        return UrssafReference::fromString(self::URSSAF_REF);
    }

    public static function snapshot(?string $status, ?string $paymentStatus): SubmissionSnapshot
    {
        return new SubmissionSnapshot($status, $paymentStatus, [], ReportFiles::none(), [], null);
    }

    /**
     * A submission brought to the state through the public API at {@see self::NOW}, its events released.
     */
    public static function submissionIn(SubmissionState $state, SubmissionIdentity $identity): Submission
    {
        $now = self::now();
        $submission = Submission::start($identity, $now);
        if ($state->isKnownToUrssaf()) {
            self::processed($submission, $state, $now);
        }
        if ($state === SubmissionState::OutcomeUnknown || $state === SubmissionState::NotReceived) {
            $submission->markOutcomeUnknown($now);
        }
        if ($state->isReclaimable()) {
            self::failed($submission, $state, $now);
        }
        $submission->releaseEvents();

        return $submission;
    }

    private static function processed(Submission $submission, SubmissionState $state, Instant $now): void
    {
        $submission->markUploaded(self::reference(), $now);
        if ($state !== SubmissionState::InProcess) {
            $submission->applySnapshot(self::snapshot(strtoupper($state->value), null), $now);
        }
    }

    private static function failed(Submission $submission, SubmissionState $state, Instant $now): void
    {
        match ($state) {
            SubmissionState::UploadRejected => $submission->markUploadRejected([], $now),
            SubmissionState::Error => $submission->markFailed($now),
            SubmissionState::InvalidCredentials => $submission->markInvalidCredentials($now),
            SubmissionState::NotReceived => $submission->markNotReceived($now),
            SubmissionState::Uploading,
            SubmissionState::InProcess,
            SubmissionState::Accepted,
            SubmissionState::Rejected,
            SubmissionState::OutcomeUnknown => throw new LogicException('Not a reclaimable state.'),
        };
    }

}
