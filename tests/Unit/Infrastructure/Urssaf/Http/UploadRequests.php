<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StringFileSource;

final class UploadRequests
{
    private function __construct() {}

    public static function of(Operation $operation = Operation::UploadIdentification): ApiRequest
    {
        return ApiRequest::multipart(
            $operation,
            '/p/upload',
            [],
            new StringFileSource(FileName::fromString('DPID_1.xml.gz'), 'bytes'),
        );
    }
}
