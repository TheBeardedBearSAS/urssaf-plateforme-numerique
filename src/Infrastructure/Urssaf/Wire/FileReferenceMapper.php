<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Reads a `FileRef` object (`{name, fileId}`, api-reference section 2).
 */
final class FileReferenceMapper
{
    /**
     * Null when the field is absent or has no `fileId` (nothing can be downloaded without it).
     *
     * @throws UnexpectedUrssafResponse when the field or its content has the wrong type, or `fileId` is not a valid reference
     */
    public static function field(Payload $parent, string $key): ?FileReference
    {
        $reference = $parent->object($key);
        $fileId = $reference?->optionalString('fileId') ?? '';
        if (!$reference instanceof Payload || $fileId === '') {
            return null;
        }

        try {
            return FileReference::of($fileId, $reference->optionalString('name'));
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy(\sprintf('field "%s.fileId" is not a valid reference', $key), $exception);
        }
    }
}
