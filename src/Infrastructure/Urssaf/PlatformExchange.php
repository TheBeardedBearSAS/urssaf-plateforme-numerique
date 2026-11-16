<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\PageInfo;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\PageInfoMapper;

/**
 * The calls of {@see UrssafHttpGateway} on behalf of a tenant: the tenant credentials are resolved for every call.
 *
 * @internal
 */
final readonly class PlatformExchange
{
    public function __construct(private CredentialsProvider $credentialsProvider, private JsonApiClient $client) {}

    public function send(TenantId $tenantId, ApiRequest $request): ApiResponse
    {
        return $this->client->send($tenantId, $this->credentialsProvider->forTenant($tenantId), $request);
    }

    public function download(TenantId $tenantId, Operation $operation, string $path): DownloadedFile
    {
        $response = $this->send($tenantId, ApiRequest::download($operation, $path));

        return new DownloadedFile(
            $response->stream(),
            $response->header('Content-Type'),
            $response->header('Content-Disposition'),
        );
    }

    /**
     * Paging of a search or news feed answer, from its `UCN-*` headers.
     */
    public static function pageInfo(ApiResponse $response): PageInfo
    {
        return PageInfoMapper::fromHeaders(
            $response->header('UCN-Total-Pages'),
            $response->header('UCN-Total-Count'),
            $response->header('UCN-Current-Page'),
            $response->header('UCN-Per-Page'),
        );
    }
}
