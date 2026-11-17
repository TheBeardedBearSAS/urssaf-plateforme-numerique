<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadDeclaration;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadDeclarationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadReport;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\InMemoryFileSource;

/**
 * Drives an application through the use cases to a given state (official file names of the API reference).
 */
final class Scenario
{
    public const TENANT = 'tenant-1';
    public const EXTERNAL_REFERENCE = 'submission-1';
    public const IDENTIFICATION_FILE = 'DPID_202505_24001_000_20250428150823.xml.gz';
    public const DECLARATION_FILE = 'DPDE_202505_24001_000_20250428150823.xml.gz';
    public const REGULARISATION_FILE = 'DPRE_202505_24001_000_20250428150823.xml.gz';
    public const FILE_CONTENT = 'fictitious compressed XML';

    private function __construct() {}

    /**
     * An identification file uploaded through the use case (state "in_process" when URSSAF accepted the upload).
     */
    public static function uploadedIdentification(Harness $harness, string $tenant = self::TENANT, string $reference = self::EXTERNAL_REFERENCE): UploadReport
    {
        return $harness->get(UploadIdentificationHandler::class)(UploadIdentification::of(
            TenantId::fromString($tenant),
            ExternalReference::fromString($reference),
            InMemoryFileSource::of(FileName::fromString(self::IDENTIFICATION_FILE), self::FILE_CONTENT),
        ));
    }

    /**
     * A declaration (or regularisation) file uploaded through the use case.
     */
    public static function uploadedDeclaration(Harness $harness, DeclarationType $type = DeclarationType::Declaration, string $tenant = self::TENANT, string $reference = self::EXTERNAL_REFERENCE): UploadReport
    {
        $name = $type === DeclarationType::Declaration ? self::DECLARATION_FILE : self::REGULARISATION_FILE;

        return $harness->get(UploadDeclarationHandler::class)(UploadDeclaration::of(
            TenantId::fromString($tenant),
            ExternalReference::fromString($reference),
            $type,
            InMemoryFileSource::of(FileName::fromString($name), self::FILE_CONTENT),
        ));
    }
}
