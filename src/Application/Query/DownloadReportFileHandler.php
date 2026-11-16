<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Query;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;

final readonly class DownloadReportFileHandler
{
    public function __construct(private PlatformGateway $gateway, private PlatformResolver $platforms) {}

    /**
     * The caller owns the returned stream and must close it.
     */
    public function __invoke(DownloadReportFile $query): DownloadedFile
    {
        $platform = $this->platforms->forTenant($query->tenantId);

        return match ($query->kind) {
            SubmissionKind::Identification => $this->gateway->downloadIdentificationFile(
                $query->tenantId,
                $platform,
                $query->reference,
                $query->file,
            ),
            SubmissionKind::Declaration => $this->gateway->downloadDeclarationFile(
                $query->tenantId,
                $platform,
                $query->reference,
                $query->file,
            ),
        };
    }
}
