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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissionsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReconcileReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReportItem;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SyncReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadReport;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\Violation;

/**
 * Execution guard and output of the console commands. Only identifiers, states, codes and package messages are
 * printed: never a URSSAF description, nor the uploader of a file (ADR 0009, ADR 0012).
 */
final class CommandReports
{
    /** Outcomes of a poll that make the command exit with a failure status. */
    private const FAILED_SYNC_ITEMS = [
        SyncSubmissionStatusesHandler::FAILED,
        SyncSubmissionStatusesHandler::INVALID_CREDENTIALS,
    ];

    /** Outcomes of a reconciliation that make the command exit with a failure status. */
    private const FAILED_RECONCILE_ITEMS = [
        ReconcileUnknownSubmissionsHandler::FAILED,
        ReconcileUnknownSubmissionsHandler::INVALID_CREDENTIALS,
        ReconcileUnknownSubmissionsHandler::MANUAL_REVIEW,
    ];

    /** Outcomes of an upload that make the command exit with a failure status. */
    private const FAILED_UPLOADS = [
        UploadOutcome::Rejected,
        UploadOutcome::InvalidCredentials,
        UploadOutcome::OutcomeUnknown,
        UploadOutcome::Exhausted,
    ];

    /**
     * Runs a command body. An invalid usage (option, input refused by the domain) prints its errors and gives
     * Command::INVALID (2); any other package failure prints its message and gives Command::FAILURE (1).
     *
     * @param callable(): int $action
     * @param-immediately-invoked-callable $action
     */
    public static function guard(OutputInterface $output, callable $action): int
    {
        try {
            return $action();
        } catch (InvalidOptionException $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));

            return Command::INVALID;
        } catch (ValidationFailed $failure) {
            $errors = $failure->result()->errors();
            array_walk($errors, static fn(Violation $error) => $output->writeln(\sprintf('<error>%s: %s (%s)</error>', $error->path(), $error->message(), $error->code())));
            $output->writeln(\sprintf('<error>Invalid input: %d error(s).</error>', \count($errors)));

            return Command::INVALID;
        } catch (InvalidValue $failure) {
            $output->writeln(\sprintf('<error>%s (%s)</error>', $failure->getMessage(), $failure->violationCode()));

            return Command::INVALID;
        } catch (UrssafException $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));

            return Command::FAILURE;
        }
    }

    /**
     * Prints the items of a poll or reconciliation report as a table followed by the count of each outcome.
     */
    public static function renderReport(OutputInterface $output, SyncReport|ReconcileReport $report): int
    {
        $table = new Table($output);
        $table->setHeaders(['Submission', 'Outcome']);
        $table->setRows(array_map(static fn(ReportItem $item): array => [$item->submissionId, $item->outcome], $report->items()));
        $table->render();

        $counts = [];
        foreach ($report->items() as $item) {
            $counts[$item->outcome] = ($counts[$item->outcome] ?? 0) + 1;
        }
        ksort($counts);
        $output->writeln(\sprintf(
            '%d submission(s) processed%s.',
            $report->total(),
            $counts === [] ? '' : ' (' . implode(', ', array_map(static fn(string $outcome, int $count): string => \sprintf('%s: %d', $outcome, $count), array_keys($counts), $counts)) . ')',
        ));

        $failed = $report instanceof SyncReport ? self::FAILED_SYNC_ITEMS : self::FAILED_RECONCILE_ITEMS;

        return array_intersect(array_keys($counts), $failed) === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Prints the result of an upload: what was done and the record as it is after it (error codes only).
     */
    public static function renderUpload(OutputInterface $output, UploadReport $report): int
    {
        $errors = array_map(static fn(SubmissionError $error): string => $error->code() ?? '-', $report->errors);
        self::renderFields($output, [
            ['Submission', $report->submissionId->value()],
            ['Outcome', $report->outcome->value],
            ['State', $report->state->value],
            ['URSSAF reference', $report->urssafReference?->value() ?? '-'],
            ['Errors', $errors === [] ? '-' : implode(', ', $errors)],
        ]);
        if ($report->outcome === UploadOutcome::OutcomeUnknown) {
            $output->writeln('The file may have reached URSSAF: do not upload it again, reconcile it ("urssaf:pn:submissions:reconcile") or resolve it ("urssaf:pn:submission:resolve").');
        }

        return \in_array($report->outcome, self::FAILED_UPLOADS, true) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Tells that the same operation is already running (its lock is held): nothing is done, which is not a failure.
     */
    public static function renderBusy(OutputInterface $output, string $resource): int
    {
        $output->writeln(\sprintf('<comment>Already running (lock "%s" is held): skipped.</comment>', $resource));

        return Command::SUCCESS;
    }

    /**
     * Prints what a dry run would process.
     *
     * @param list<array{string, string}> $rows tenant id and submission id
     */
    public static function renderPlan(OutputInterface $output, array $rows): int
    {
        $table = new Table($output);
        $table->setHeaders(['Tenant', 'Submission']);
        $table->setRows($rows);
        $table->render();
        $output->writeln(\sprintf('Dry run: %d submission(s) would be processed, URSSAF was not called.', \count($rows)));

        return Command::SUCCESS;
    }

    /**
     * Prints a view as a two-column table.
     *
     * @param list<array{string, string}> $rows field and value
     */
    public static function renderFields(OutputInterface $output, array $rows): void
    {
        $table = new Table($output);
        $table->setHeaders(['Field', 'Value']);
        $table->setRows($rows);
        $table->render();
    }
}
