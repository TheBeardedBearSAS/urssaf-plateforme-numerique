<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;

/**
 * Builds the `multipart/form-data` body of the two uploads (ADR 0002): a `name` text part and a `data` file part
 * (`filename` = the same name, `Content-Type: application/octet-stream`). The body is assembled in a `php://temp`
 * stream, so memory stays bounded whatever the file size.
 *
 * @internal
 */
final readonly class MultipartBody
{
    private const CHUNK_SIZE = 65_536;
    private const EOL = "\r\n";

    public function __construct(private StreamFactoryInterface $streamFactory) {}

    /**
     * @param resource $content readable stream of the file, read to its end and left open for the caller to close
     *
     * @return array{0: StreamInterface, 1: string} the body, positioned at its start, and its `Content-Type` value
     */
    public function build(FileName $name, mixed $content, string $boundary): array
    {
        $fileName = $name->value();
        $body = $this->streamFactory->createStreamFromFile('php://temp', 'w+b');
        $body->write(
            '--' . $boundary . self::EOL
            . 'Content-Disposition: form-data; name="name"' . self::EOL . self::EOL
            . $fileName . self::EOL
            . '--' . $boundary . self::EOL
            . 'Content-Disposition: form-data; name="data"; filename="' . $fileName . '"' . self::EOL
            . 'Content-Type: application/octet-stream' . self::EOL . self::EOL,
        );

        $source = $this->streamFactory->createStreamFromResource($content);

        try {
            while (!$source->eof()) {
                $body->write($source->read(self::CHUNK_SIZE));
            }
        } finally {
            $source->detach();
        }

        $body->write(self::EOL . '--' . $boundary . '--' . self::EOL);
        $body->rewind();

        return [$body, 'multipart/form-data; boundary=' . $boundary];
    }
}
