<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port;

/**
 * URSSAF API operations, backed by the OpenAPI operationId verbatim (ADR 0001).
 */
enum Operation: string
{
    case UploadIdentification = 'uploadPlatformIdentification';
    case GetIdentification = 'getPlatformIdentification';
    case SearchIdentifications = 'searchPlatformIdentification';
    case DownloadIdentificationFile = 'downloadIdentificationRelatedFile';
    case UploadDeclaration = 'uploadPlatformDeclaration';
    case GetDeclaration = 'getPlatformDeclaration';
    case SearchDeclarations = 'searchPlatformDeclaration';
    case DownloadDeclarationFile = 'downloadDeclarationRelatedFile';
    case GetNewsFeed = 'getPlatformNewsFeed';

    /**
     * True for the two operations that send a file: they are never resent blindly (ADR 0003).
     */
    public function isUpload(): bool
    {
        return match ($this) {
            self::UploadIdentification, self::UploadDeclaration => true,
            self::GetIdentification,
            self::SearchIdentifications,
            self::DownloadIdentificationFile,
            self::GetDeclaration,
            self::SearchDeclarations,
            self::DownloadDeclarationFile,
            self::GetNewsFeed => false,
        };
    }
}
