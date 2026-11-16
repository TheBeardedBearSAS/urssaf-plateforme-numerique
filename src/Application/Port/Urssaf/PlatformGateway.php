<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * The URSSAF platform API (identification and declaration submissions, news feed), one call per method.
 *
 * Every method may throw, besides the exceptions listed: {@see UrssafAuthenticationFailed} (credentials or
 * habilitation refused), {@see UrssafUnavailable} (transient failure, retryable), {@see RateLimitExceeded} (local
 * throttling, retryable), {@see RecordNotFound} "credentials.not_found" (no credentials for the tenant), and any
 * {@see \TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException} for an answer the contract does
 * not describe (not retryable).
 */
interface PlatformGateway
{
    /**
     * @throws UrssafRequestRejected when URSSAF refuses the upload (HTTP 400)
     * @throws OutcomeUnknown        when the upload may have reached URSSAF (ADR 0003)
     */
    public function uploadIdentification(TenantId $tenantId, PlatformId $platformId, FileSource $file): UrssafReference;

    /**
     * @throws UrssafRequestRejected when URSSAF refuses the upload (HTTP 400)
     * @throws OutcomeUnknown        when the upload may have reached URSSAF (ADR 0003)
     */
    public function uploadDeclaration(
        TenantId $tenantId,
        PlatformId $platformId,
        DeclarationType $type,
        FileSource $file,
    ): UrssafReference;

    /**
     * @throws UrssafResourceNotFound when URSSAF does not know the reference
     * @throws UrssafRequestRejected
     */
    public function getIdentification(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
    ): IdentificationSubmission;

    /**
     * @return Page<IdentificationSubmission>
     *
     * @throws UrssafRequestRejected
     */
    public function searchIdentifications(TenantId $tenantId, PlatformId $platformId, SubmissionSearch $search): Page;

    /**
     * @throws UrssafResourceNotFound when the submission or the file does not exist
     * @throws UrssafRequestRejected
     */
    public function downloadIdentificationFile(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
        FileReference $file,
    ): DownloadedFile;

    /**
     * @throws UrssafResourceNotFound when URSSAF does not know the reference
     * @throws UrssafRequestRejected
     */
    public function getDeclaration(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
    ): DeclarationSubmission;

    /**
     * @return Page<DeclarationSubmission>
     *
     * @throws UrssafRequestRejected
     */
    public function searchDeclarations(TenantId $tenantId, PlatformId $platformId, SubmissionSearch $search): Page;

    /**
     * @throws UrssafResourceNotFound when the submission or the file does not exist
     * @throws UrssafRequestRejected
     */
    public function downloadDeclarationFile(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
        FileReference $file,
    ): DownloadedFile;

    /**
     * @return Page<NewsItem>
     *
     * @throws OperationNotSupported on the test API
     * @throws UrssafRequestRejected
     */
    public function newsFeed(TenantId $tenantId, PlatformId $platformId, PageRequest $page): Page;
}
