<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Service;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ContentFingerprint;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;

/**
 * Computes the {@see ContentFingerprint} of an upload (ADR 0004): the SHA-256 of the kind, the declaration type (empty
 * for an identification), the file name and the SHA-256 of the content, separated by "|". The content is streamed once,
 * in chunks, and never loaded in memory (ADR 0005); the stream is always closed.
 */
final class FileFingerprinter
{
    public const CHUNK_SIZE = 65_536;

    private const ALGORITHM = 'sha256';

    private const SEPARATOR = '|';

    /**
     * @throws InvalidValue "file_source.unreadable" when the content cannot be read
     */
    public static function fingerprint(FileSource $file, SubmissionKind $kind, ?DeclarationType $type): ContentFingerprint
    {
        return ContentFingerprint::fromHex(hash(self::ALGORITHM, implode(self::SEPARATOR, [
            $kind->value,
            $type->value ?? '',
            $file->name()->value(),
            self::contentDigest($file),
        ])));
    }

    /**
     * @throws InvalidValue "file_source.unreadable"
     */
    private static function contentDigest(FileSource $file): string
    {
        $stream = $file->open();
        try {
            $context = hash_init(self::ALGORITHM);
            while (!feof($stream)) {
                $chunk = fread($stream, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw InvalidValue::because('file_source.unreadable', 'The content of the file cannot be read.');
                }
                hash_update($context, $chunk);
            }

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
