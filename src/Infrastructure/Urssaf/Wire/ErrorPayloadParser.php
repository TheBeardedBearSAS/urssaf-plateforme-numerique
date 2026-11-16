<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Parses the error payload of the API: an array of `{code,message,description}` objects (api-reference section 1.3),
 * or a single such object. The codes are not published, so they are kept verbatim (only trimmed).
 */
final class ErrorPayloadParser
{
    /**
     * Lenient: an unreadable body yields no error (used for HTTP error responses, which may be anything).
     */
    public function parseBody(string $body): ApiErrors
    {
        try {
            return $this->parsePayload(JsonDecoder::decode($body));
        } catch (UnexpectedUrssafResponse) {
            return ApiErrors::none();
        }
    }

    /**
     * Entries without a code are ignored.
     *
     * @throws UnexpectedUrssafResponse when the payload is a list that does not contain only objects
     */
    public function parsePayload(Payload $payload): ApiErrors
    {
        $errors = [];
        foreach ($payload->isList() ? $payload->entries() : [$payload] as $entry) {
            $code = trim($entry->string('code') ?? '');
            if ($code === '') {
                continue;
            }

            $errors[] = ApiError::of($code, $entry->string('message') ?? '', $entry->string('description') ?? '');
        }

        return ApiErrors::of(...$errors);
    }
}
