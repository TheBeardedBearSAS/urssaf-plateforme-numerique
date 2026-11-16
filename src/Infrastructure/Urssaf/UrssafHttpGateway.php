<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\DeclarationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\IdentificationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\NewsFeedMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\QueryEncoder;

/**
 * HTTP implementation of {@see PlatformGateway}: the nine operations of the URSSAF platform API. Build it with
 * {@see UrssafGatewayFactory}.
 */
final readonly class UrssafHttpGateway implements PlatformGateway
{
    public function __construct(private PlatformExchange $exchange, private ApiConfiguration $configuration) {}

    public function uploadIdentification(TenantId $tenantId, PlatformId $platformId, FileSource $file): UrssafReference
    {
        $response = $this->exchange->send($tenantId, ApiRequest::multipart(
            Operation::UploadIdentification,
            PlatformPaths::identifications($platformId) . '/upload',
            [],
            $file,
        ));

        return UploadReferenceReader::read(Operation::UploadIdentification, $response);
    }

    public function uploadDeclaration(
        TenantId $tenantId,
        PlatformId $platformId,
        DeclarationType $type,
        FileSource $file,
    ): UrssafReference {
        $response = $this->exchange->send($tenantId, ApiRequest::multipart(
            Operation::UploadDeclaration,
            PlatformPaths::declarations($platformId) . '/upload',
            [['type', $type->value]],
            $file,
        ));

        return UploadReferenceReader::read(Operation::UploadDeclaration, $response);
    }

    public function getIdentification(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
    ): IdentificationSubmission {
        $response = $this->exchange->send($tenantId, ApiRequest::get(
            Operation::GetIdentification,
            PlatformPaths::identification($platformId, $reference),
            [],
        ));

        return IdentificationMapper::map($response->payload(), $this->configuration->apiTimeZone());
    }

    public function searchIdentifications(TenantId $tenantId, PlatformId $platformId, SubmissionSearch $search): Page
    {
        $response = $this->exchange->send($tenantId, ApiRequest::get(
            Operation::SearchIdentifications,
            PlatformPaths::identifications($platformId),
            QueryEncoder::identificationSearch($search),
        ));

        return Page::of(
            IdentificationMapper::mapList($response->payload(), $this->configuration->apiTimeZone()),
            PlatformExchange::pageInfo($response),
        );
    }

    public function downloadIdentificationFile(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
        FileReference $file,
    ): DownloadedFile {
        return $this->exchange->download(
            $tenantId,
            Operation::DownloadIdentificationFile,
            PlatformPaths::identificationFile($platformId, $reference, $file),
        );
    }

    public function getDeclaration(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
    ): DeclarationSubmission {
        $response = $this->exchange->send($tenantId, ApiRequest::get(
            Operation::GetDeclaration,
            PlatformPaths::declaration($platformId, $reference),
            [],
        ));

        return DeclarationMapper::map($response->payload(), $this->configuration->apiTimeZone());
    }

    public function searchDeclarations(TenantId $tenantId, PlatformId $platformId, SubmissionSearch $search): Page
    {
        $response = $this->exchange->send($tenantId, ApiRequest::get(
            Operation::SearchDeclarations,
            PlatformPaths::declarations($platformId),
            QueryEncoder::declarationSearch($search),
        ));

        return Page::of(
            DeclarationMapper::mapList($response->payload(), $this->configuration->apiTimeZone()),
            PlatformExchange::pageInfo($response),
        );
    }

    public function downloadDeclarationFile(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
        FileReference $file,
    ): DownloadedFile {
        return $this->exchange->download(
            $tenantId,
            Operation::DownloadDeclarationFile,
            PlatformPaths::declarationFile($platformId, $reference, $file),
        );
    }

    public function newsFeed(TenantId $tenantId, PlatformId $platformId, PageRequest $page): Page
    {
        if (!$this->configuration->environment()->supportsNewsFeed()) {
            throw OperationNotSupported::newsFeedOnTestApi();
        }

        $response = $this->exchange->send($tenantId, ApiRequest::get(
            Operation::GetNewsFeed,
            PlatformPaths::newsFeed($platformId),
            QueryEncoder::pageRequest($page),
        ));

        return Page::of(NewsFeedMapper::mapList($response->payload()), PlatformExchange::pageInfo($response));
    }
}
