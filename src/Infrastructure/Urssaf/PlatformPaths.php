<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Resource paths of the platform API, relative to the base path of the target (api-reference section 2). Every
 * variable segment is percent-encoded.
 *
 * @internal
 */
final class PlatformPaths
{
    public static function identifications(PlatformId $platformId): string
    {
        return self::platform($platformId) . '/identifications';
    }

    public static function identification(PlatformId $platformId, UrssafReference $reference): string
    {
        return self::identifications($platformId) . '/' . rawurlencode($reference->value());
    }

    public static function identificationFile(PlatformId $platformId, UrssafReference $reference, FileReference $file): string
    {
        return self::identification($platformId, $reference) . '/file/' . rawurlencode($file->fileId());
    }

    public static function declarations(PlatformId $platformId): string
    {
        return self::platform($platformId) . '/declarations';
    }

    public static function declaration(PlatformId $platformId, UrssafReference $reference): string
    {
        return self::declarations($platformId) . '/' . rawurlencode($reference->value());
    }

    public static function declarationFile(PlatformId $platformId, UrssafReference $reference, FileReference $file): string
    {
        return self::declaration($platformId, $reference) . '/file/' . rawurlencode($file->fileId());
    }

    public static function newsFeed(PlatformId $platformId): string
    {
        return self::platform($platformId) . '/news-feed';
    }

    private static function platform(PlatformId $platformId): string
    {
        return '/platforms/' . rawurlencode($platformId->value());
    }
}
