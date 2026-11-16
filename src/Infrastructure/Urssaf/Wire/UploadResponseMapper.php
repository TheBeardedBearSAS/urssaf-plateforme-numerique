<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Reads the answer of the two upload operations: `{"urssafRef": "..."}` (api-reference sections 2.1 and 2.5).
 */
final class UploadResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse when `urssafRef` is missing, empty or not a valid reference
     */
    public static function urssafReference(Payload $response): UrssafReference
    {
        try {
            return UrssafReference::fromString($response->requireString('urssafRef'));
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('field "urssafRef" is not a valid reference', $exception);
        }
    }
}
