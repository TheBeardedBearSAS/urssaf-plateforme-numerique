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

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ContentFingerprint;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Fictitious values of the submission "<number>": id "sub-<number>", external reference "ref-<number>", file
 * "file-<number>.zip", urssafRef "urssaf-<number>".
 */
final class SubmissionSamples
{
    private function __construct() {}

    public static function fileName(int $number): FileName
    {
        return FileName::fromString('file-' . $number . '.zip');
    }

    public static function fingerprint(int $number): ContentFingerprint
    {
        return ContentFingerprint::fromHex(hash('sha256', 'content-' . $number));
    }

    public static function urssafReference(int $number): UrssafReference
    {
        return UrssafReference::fromString('urssaf-' . $number);
    }

    public static function identity(
        int $number,
        SubmissionKind $kind,
        ?DeclarationType $type,
        string $tenant = Harness::TENANT,
    ): SubmissionIdentity {
        return new SubmissionIdentity(
            SubmissionId::fromString('sub-' . $number),
            TenantId::fromString($tenant),
            PlatformId::fromString($tenant === Harness::TENANT ? Harness::PLATFORM : Harness::OTHER_PLATFORM),
            ExternalReference::fromString('ref-' . $number),
            $kind,
            $type,
            self::fileName($number),
            self::fingerprint($number),
        );
    }

    public static function snapshot(?string $status, ?string $paymentStatus): SubmissionSnapshot
    {
        return new SubmissionSnapshot($status, $paymentStatus, [], ReportFiles::none(), [], null);
    }
}
