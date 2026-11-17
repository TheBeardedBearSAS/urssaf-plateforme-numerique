<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFileHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use Throwable;

/**
 * Downloads a report file of a submission from URSSAF to a new local file, streamed (ADR 0005). The file is never
 * overwritten.
 */
#[AsCommand(name: 'urssaf:pn:report:download', description: 'Download a report file of a submission from URSSAF')]
final class DownloadReportCommand extends Command
{
    private const ARGUMENT_ID = 'id';
    private const ARGUMENT_FILE_ID = 'fileId';
    private const OPTION_OUTPUT = 'output';

    public function __construct(
        private readonly GetSubmissionHandler $get,
        private readonly DownloadReportFileHandler $download,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_ID, InputArgument::REQUIRED, 'Id of the submission');
        $this->addArgument(self::ARGUMENT_FILE_ID, InputArgument::REQUIRED, 'Id of the report file ("fileId" of the report)');
        $this->addOption(self::OPTION_OUTPUT, null, InputOption::VALUE_REQUIRED, 'Path of the file to create (required, must not exist)');
        CommandOptions::addTenant($this, 'Tenant id (required)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::requiredTenant($input);
            $path = CommandInputs::requiredText($input, self::OPTION_OUTPUT);
            $file = FileReference::of(CommandInputs::requiredArgument($input, self::ARGUMENT_FILE_ID), null);
            $view = ($this->get)(new GetSubmission($tenant, SubmissionId::fromString(CommandInputs::requiredArgument($input, self::ARGUMENT_ID))));
            if ($view->urssaf->urssafRef === null) {
                $output->writeln('<error>The submission has no URSSAF reference yet: there is no report to download.</error>');

                return Command::FAILURE;
            }
            $reference = UrssafReference::fromString($view->urssaf->urssafRef);
            $downloaded = ($this->download)(SubmissionKind::from($view->kind) === SubmissionKind::Identification
                ? DownloadReportFile::identification($tenant, $reference, $file)
                : DownloadReportFile::declaration($tenant, $reference, $file));
            $stream = $downloaded->stream();
            try {
                $target = $this->create($path);
                if (\is_string($target)) {
                    $output->writeln(\sprintf('<error>%s</error>', $target));

                    return Command::FAILURE;
                }
                try {
                    $bytes = $stream->copyTo($target);
                } catch (Throwable $failure) {
                    fclose($target);
                    unlink($path);

                    throw $failure;
                }
            } finally {
                $stream->close();
            }
            $output->writeln(\sprintf('%d byte(s) written to "%s".', $bytes, $path));

            return Command::SUCCESS;
        });
    }

    /**
     * The new file, opened for writing (PHP closes it with its last reference); the reason why it cannot be created
     * otherwise.
     *
     * @return resource|string
     */
    private function create(string $path): mixed
    {
        if (!is_dir(\dirname($path))) {
            return \sprintf('The directory of the output file "%s" does not exist.', $path);
        }
        if (file_exists($path)) {
            return \sprintf('The output file "%s" already exists: it is never overwritten.', $path);
        }

        $target = fopen($path, 'x+b');

        return $target === false ? \sprintf('The output file "%s" cannot be created.', $path) : $target;
    }
}
