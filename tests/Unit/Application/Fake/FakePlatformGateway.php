<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
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
use Throwable;

/**
 * Scripted gateway: each method answers with the next response scripted for it (a value, or a throwable to throw), and
 * fails with a LogicException when nothing is scripted. Calls are journaled and recorded, with the tenant and platform
 * of each one.
 *
 * Implements the whole gateway port, one method per operation, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class FakePlatformGateway implements PlatformGateway
{
    /** @var ResponseQueue<UrssafReference> */
    private ResponseQueue $identificationUploads;

    /** @var ResponseQueue<UrssafReference> */
    private ResponseQueue $declarationUploads;

    /** @var ResponseQueue<IdentificationSubmission> */
    private ResponseQueue $identificationReads;

    /** @var ResponseQueue<DeclarationSubmission> */
    private ResponseQueue $declarationReads;

    /** @var ResponseQueue<Page<IdentificationSubmission>> */
    private ResponseQueue $identificationSearches;

    /** @var ResponseQueue<Page<DeclarationSubmission>> */
    private ResponseQueue $declarationSearches;

    /** @var ResponseQueue<DownloadedFile> */
    private ResponseQueue $identificationDownloads;

    /** @var ResponseQueue<DownloadedFile> */
    private ResponseQueue $declarationDownloads;

    /** @var ResponseQueue<Page<NewsItem>> */
    private ResponseQueue $newsFeeds;

    private GatewayCalls $recorded;

    public function __construct(Journal $journal = new Journal())
    {
        $this->identificationUploads = new ResponseQueue();
        $this->declarationUploads = new ResponseQueue();
        $this->identificationReads = new ResponseQueue();
        $this->declarationReads = new ResponseQueue();
        $this->identificationSearches = new ResponseQueue();
        $this->declarationSearches = new ResponseQueue();
        $this->identificationDownloads = new ResponseQueue();
        $this->declarationDownloads = new ResponseQueue();
        $this->newsFeeds = new ResponseQueue();
        $this->recorded = new GatewayCalls($journal);
    }

    public function willUploadIdentification(UrssafReference|Throwable ...$responses): self
    {
        $this->identificationUploads->push(...$responses);

        return $this;
    }

    public function willUploadDeclaration(UrssafReference|Throwable ...$responses): self
    {
        $this->declarationUploads->push(...$responses);

        return $this;
    }

    public function willGetIdentification(IdentificationSubmission|Throwable ...$responses): self
    {
        $this->identificationReads->push(...$responses);

        return $this;
    }

    public function willGetDeclaration(DeclarationSubmission|Throwable ...$responses): self
    {
        $this->declarationReads->push(...$responses);

        return $this;
    }

    /**
     * @param Page<IdentificationSubmission>|Throwable ...$responses
     */
    public function willSearchIdentifications(Page|Throwable ...$responses): self
    {
        $this->identificationSearches->push(...$responses);

        return $this;
    }

    /**
     * @param Page<DeclarationSubmission>|Throwable ...$responses
     */
    public function willSearchDeclarations(Page|Throwable ...$responses): self
    {
        $this->declarationSearches->push(...$responses);

        return $this;
    }

    public function willDownloadIdentificationFile(DownloadedFile|Throwable ...$responses): self
    {
        $this->identificationDownloads->push(...$responses);

        return $this;
    }

    public function willDownloadDeclarationFile(DownloadedFile|Throwable ...$responses): self
    {
        $this->declarationDownloads->push(...$responses);

        return $this;
    }

    /**
     * @param Page<NewsItem>|Throwable ...$responses
     */
    public function willReadNewsFeed(Page|Throwable ...$responses): self
    {
        $this->newsFeeds->push(...$responses);

        return $this;
    }

    public function uploadIdentification(TenantId $tenantId, PlatformId $platformId, FileSource $file): UrssafReference
    {
        $this->recorded->record('upload_identification:' . $file->name()->value(), $tenantId, $platformId);

        return $this->identificationUploads->next('uploadIdentification');
    }

    public function uploadDeclaration(
        TenantId $tenantId,
        PlatformId $platformId,
        DeclarationType $type,
        FileSource $file,
    ): UrssafReference {
        $this->recorded->record('upload_declaration:' . $type->value . ':' . $file->name()->value(), $tenantId, $platformId);

        return $this->declarationUploads->next('uploadDeclaration');
    }

    public function getIdentification(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
    ): IdentificationSubmission {
        $this->recorded->record('get_identification:' . $reference->value(), $tenantId, $platformId);

        return $this->identificationReads->next('getIdentification');
    }

    public function searchIdentifications(TenantId $tenantId, PlatformId $platformId, SubmissionSearch $search): Page
    {
        $this->recorded->record('search_identifications', $tenantId, $platformId);
        $this->recorded->search($search);

        return $this->identificationSearches->next('searchIdentifications');
    }

    public function downloadIdentificationFile(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
        FileReference $file,
    ): DownloadedFile {
        $this->recorded->record('download_identification:' . $reference->value() . ':' . $file->fileId(), $tenantId, $platformId);

        return $this->identificationDownloads->next('downloadIdentificationFile');
    }

    public function getDeclaration(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
    ): DeclarationSubmission {
        $this->recorded->record('get_declaration:' . $reference->value(), $tenantId, $platformId);

        return $this->declarationReads->next('getDeclaration');
    }

    public function searchDeclarations(TenantId $tenantId, PlatformId $platformId, SubmissionSearch $search): Page
    {
        $this->recorded->record('search_declarations', $tenantId, $platformId);
        $this->recorded->search($search);

        return $this->declarationSearches->next('searchDeclarations');
    }

    public function downloadDeclarationFile(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
        FileReference $file,
    ): DownloadedFile {
        $this->recorded->record('download_declaration:' . $reference->value() . ':' . $file->fileId(), $tenantId, $platformId);

        return $this->declarationDownloads->next('downloadDeclarationFile');
    }

    public function newsFeed(TenantId $tenantId, PlatformId $platformId, PageRequest $page): Page
    {
        $this->recorded->record('news_feed', $tenantId, $platformId);
        $this->recorded->newsFeed($page);

        return $this->newsFeeds->next('newsFeed');
    }

    public function recorded(): GatewayCalls
    {
        return $this->recorded;
    }
}
