<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFileHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\DownloadReportCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

/**
 * What happens to the downloaded stream, which the command owns and must close.
 */
final class DownloadReportStreamTest extends TestCase
{
    public function testTheStreamIsClosedAfterTheCopy(): void
    {
        $stream = new RecordingStream(false);
        $path = ScratchFiles::directory() . '/' . bin2hex(random_bytes(4)) . '-copied.xml';

        $status = $this->download($stream, $path)->getStatusCode();

        static::assertSame(0, $status);
        static::assertSame(1, $stream->closed());
        static::assertSame('report content', file_get_contents($path));
    }

    public function testTheStreamIsClosedWhenTheCopyFails(): void
    {
        $stream = new RecordingStream(true);
        $path = ScratchFiles::directory() . '/' . bin2hex(random_bytes(4)) . '-failed.xml';

        try {
            $this->download($stream, $path);
            static::fail('The failure must propagate.');
        } catch (RuntimeException) {
            static::assertSame(1, $stream->closed());
            static::assertFileDoesNotExist($path);
        }
    }

    public function testTheStreamIsClosedWhenTheFileCannotBeCreated(): void
    {
        $stream = new RecordingStream(false);

        set_error_handler(static fn(): bool => true);

        try {
            $tester = $this->download($stream, ScratchFiles::directory() . '/' . str_repeat('x', 300));
        } finally {
            restore_error_handler();
        }

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('cannot be created.', $tester->getDisplay());
        static::assertSame(1, $stream->closed());
    }

    public function testTheStreamIsClosedWhenTheFileExists(): void
    {
        $stream = new RecordingStream(false);

        $tester = $this->download($stream, ScratchFiles::file('existing.xml', 'precious content'));

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('already exists: it is never overwritten.', $tester->getDisplay());
        static::assertSame(1, $stream->closed());
    }

    public function testTheStreamIsClosedWhenTheDirectoryIsMissing(): void
    {
        $stream = new RecordingStream(false);

        $tester = $this->download($stream, ScratchFiles::directory() . '/missing-directory/report.xml');

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('directory of the output file', $tester->getDisplay());
        static::assertStringContainsString('does not exist.', $tester->getDisplay());
        static::assertSame(1, $stream->closed());
    }

    private function download(RecordingStream $stream, string $path): CommandTester
    {
        $harness = new Harness();
        $submission = $harness->identification(1, SubmissionState::InProcess);
        $harness->gateway->willDownloadIdentificationFile(new DownloadedFile($stream, null, null));
        $tester = new CommandTester(new DownloadReportCommand(new GetSubmissionHandler($harness->submissions), new DownloadReportFileHandler($harness->gateway, $harness->platforms)));

        $tester->execute(['id' => $submission->id()->value(), 'fileId' => 'file-1', '--tenant' => Harness::TENANT, '--output' => $path]);

        return $tester;
    }
}
