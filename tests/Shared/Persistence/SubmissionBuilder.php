<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence;

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
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Fictitious submissions for the repository contracts (shared by the in-memory and the Doctrine adapters). Every
 * submission is created "uploading" at the given ISO 8601 instant, with its creation events not released.
 */
final class SubmissionBuilder
{
    private const FINGERPRINT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public static function declaration(
        string $id,
        string $tenant,
        string $externalReference,
        string $createdAt,
        DeclarationType $type = DeclarationType::Declaration,
    ): Submission {
        return self::start($id, $tenant, $externalReference, $createdAt, $type);
    }

    public static function identification(string $id, string $tenant, string $externalReference, string $createdAt): Submission
    {
        return self::start($id, $tenant, $externalReference, $createdAt, null);
    }

    /**
     * Uploaded at the instant: in process with the given urssafRef.
     */
    public static function inProcess(Submission $submission, string $urssafRef, string $at): Submission
    {
        $submission->markUploaded(UrssafReference::fromString($urssafRef), Instant::fromIso8601($at));

        return $submission;
    }

    /**
     * Polled at the instant, URSSAF still processing the file.
     */
    public static function polled(Submission $submission, string $at): Submission
    {
        $submission->applySnapshot(
            new SubmissionSnapshot('IN_PROCESS', null, [], ReportFiles::none(), [], null),
            Instant::fromIso8601($at),
        );

        return $submission;
    }

    /**
     * Polled at the instant, URSSAF having processed the file with the given raw status and payment status.
     */
    public static function processed(Submission $submission, string $status, ?string $paymentStatus, string $at): Submission
    {
        $submission->applySnapshot(
            new SubmissionSnapshot($status, $paymentStatus, [], ReportFiles::none(), [], null),
            Instant::fromIso8601($at),
        );

        return $submission;
    }

    private static function start(
        string $id,
        string $tenant,
        string $externalReference,
        string $createdAt,
        ?DeclarationType $type,
    ): Submission {
        return Submission::start(
            new SubmissionIdentity(
                SubmissionId::fromString($id),
                TenantId::fromString($tenant),
                PlatformId::fromString('platform-1'),
                ExternalReference::fromString($externalReference),
                $type instanceof DeclarationType ? SubmissionKind::Declaration : SubmissionKind::Identification,
                $type,
                FileName::fromString('file-1.zip'),
                ContentFingerprint::fromHex(self::FINGERPRINT),
            ),
            Instant::fromIso8601($createdAt),
        );
    }
}
