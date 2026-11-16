<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * Reference of a file known to URSSAF (report or uploaded file): its identifier, written in a URL path, and its
 * optional name, both kept verbatim.
 */
final readonly class FileReference
{
    private const SUBJECT = 'file_reference';
    public const MAX_LENGTH = 255;

    private function __construct(
        private string $fileId,
        private ?string $name,
    ) {}

    /**
     * @throws InvalidValue "file_reference.empty", "file_reference.too_long" or "file_reference.invalid_format"
     */
    public static function of(string $fileId, ?string $name): self
    {
        if ($fileId === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (mb_strlen($fileId) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
        }
        if (str_contains($fileId, '/')) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'no "/" character');
        }

        return new self($fileId, $name);
    }

    public function fileId(): string
    {
        return $this->fileId;
    }

    public function name(): ?string
    {
        return $this->name;
    }
}
