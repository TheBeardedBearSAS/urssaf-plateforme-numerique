<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\FileFingerprinter;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ContentFingerprint;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\StreamFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\UnreadableFileSource;

final class FileFingerprinterTest extends TestCase
{
    public function testTheFingerprintOfADeclarationIsPinned(): void
    {
        // Fingerprints are persisted: changing how they are computed would break the re-entry of the uploads (ADR 0004).
        $file = StreamFileSource::named('file-1.zip', 'hello');

        $fingerprint = FileFingerprinter::fingerprint($file, SubmissionKind::Declaration, DeclarationType::Declaration);

        static::assertSame('2f88e9ab9c6af33f553c34793da141278f53ba04a6513656689f4e95209656a2', $fingerprint->value());
        static::assertSame(
            hash('sha256', 'declaration|DECLARATION|file-1.zip|2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824'),
            $fingerprint->value(),
        );
    }

    public function testTheFingerprintOfAnIdentificationHasAnEmptyType(): void
    {
        $file = StreamFileSource::named('file-1.zip', 'hello');

        $fingerprint = FileFingerprinter::fingerprint($file, SubmissionKind::Identification, null);

        static::assertSame('da640e250ba7436c16bca9149d2994984be497e7245c144213f3ca2d8c7f3006', $fingerprint->value());
    }

    public function testAContentLongerThanAChunkIsHashedWhole(): void
    {
        $content = str_repeat('0123456789abcdef', 20_000);
        $file = StreamFileSource::named('file-1.zip', $content);

        $fingerprint = FileFingerprinter::fingerprint($file, SubmissionKind::Identification, null);

        static::assertGreaterThan(FileFingerprinter::CHUNK_SIZE * 4, \strlen($content));
        static::assertSame('998463e1fea4b29cc5d3cfea8b94862607128d5a856bc1ffb4ec840c11c8d288', $fingerprint->value());
    }

    public function testAnEmptyContent(): void
    {
        $file = StreamFileSource::named('file-1.zip', '');

        $fingerprint = FileFingerprinter::fingerprint($file, SubmissionKind::Identification, null);

        static::assertSame(
            hash('sha256', 'identification||file-1.zip|e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'),
            $fingerprint->value(),
        );
    }

    public function testEveryPartOfTheUploadChangesTheFingerprint(): void
    {
        $fingerprints = [
            FileFingerprinter::fingerprint(StreamFileSource::named('a.zip', 'x'), SubmissionKind::Declaration, DeclarationType::Declaration),
            FileFingerprinter::fingerprint(StreamFileSource::named('a.zip', 'x'), SubmissionKind::Declaration, DeclarationType::Regularisation),
            FileFingerprinter::fingerprint(StreamFileSource::named('a.zip', 'x'), SubmissionKind::Identification, null),
            FileFingerprinter::fingerprint(StreamFileSource::named('b.zip', 'x'), SubmissionKind::Declaration, DeclarationType::Declaration),
            FileFingerprinter::fingerprint(StreamFileSource::named('a.zip', 'y'), SubmissionKind::Declaration, DeclarationType::Declaration),
        ];

        static::assertCount(5, array_unique(array_map(static fn(ContentFingerprint $fingerprint): string => $fingerprint->value(), $fingerprints)));
        static::assertTrue($fingerprints[0]->equals(
            FileFingerprinter::fingerprint(StreamFileSource::named('a.zip', 'x'), SubmissionKind::Declaration, DeclarationType::Declaration),
        ));
    }

    public function testTheContentIsOpenedOnceAndTheStreamIsClosed(): void
    {
        $file = StreamFileSource::named('file-1.zip', 'hello');

        FileFingerprinter::fingerprint($file, SubmissionKind::Identification, null);

        static::assertSame(1, $file->openedCount());
        static::assertTrue($file->allClosed());
    }

    public function testTheStreamIsClosedWhenTheContentCannotBeRead(): void
    {
        $file = new UnreadableFileSource();
        set_error_handler(static fn(): bool => true);

        try {
            FileFingerprinter::fingerprint($file, SubmissionKind::Identification, null);
            static::fail('An unreadable content must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame('file_source.unreadable', $exception->violationCode());
            static::assertSame('The content of the file cannot be read.', $exception->getMessage());
        } finally {
            restore_error_handler();
        }

        static::assertFalse($file->isStillOpen());
    }
}
