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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReconcileReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReportItem;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SyncReport;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CommandReports;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;

final class ItemReportTest extends TestCase
{
    /**
     * @param list<string> $outcomes
     */
    #[DataProvider('reports')]
    public function testARunFailsOnAFailedItemOnly(array $outcomes, int $status): void
    {
        $items = array_map(static fn(string $outcome, int $index): ReportItem => new ReportItem('submission-' . $index, $outcome), $outcomes, array_keys($outcomes));

        static::assertSame($status, CommandReports::renderReport(new BufferedOutput(), SyncReport::of(...$items)));
        static::assertSame($status, CommandReports::renderReport(new BufferedOutput(), ReconcileReport::of(...$items)));
    }

    /**
     * @return iterable<string, array{list<string>, int}>
     */
    public static function reports(): iterable
    {
        yield 'nothing' => [[], 0];
        foreach (['changed', 'unchanged', 'not_found', 'adopted', 'unmatched', 'ambiguous', 'in_flight', 'superseded'] as $outcome) {
            yield $outcome => [[$outcome], 0];
        }
        foreach (['failed', 'invalid_credentials'] as $outcome) {
            yield $outcome => [[$outcome], 1];
            yield $outcome . ' among others' => [['changed', $outcome, 'unchanged'], 1];
        }
    }

    public function testOnlyAReconciliationFailsOnAManualReview(): void
    {
        $item = new ReportItem('submission-1', 'manual_review');

        static::assertSame(0, CommandReports::renderReport(new BufferedOutput(), SyncReport::of($item)));
        static::assertSame(1, CommandReports::renderReport(new BufferedOutput(), ReconcileReport::of($item)));
    }

    public function testAReportIsPrintedWithTheCountOfEachOutcome(): void
    {
        $output = new BufferedOutput();

        CommandReports::renderReport($output, SyncReport::of(
            new ReportItem('submission-1', 'unchanged'),
            new ReportItem('submission-2', 'changed'),
            new ReportItem('submission-3', 'unchanged'),
        ));

        $display = $output->fetch();
        static::assertSame([['Submission', 'Outcome'], ['submission-1', 'unchanged'], ['submission-2', 'changed'], ['submission-3', 'unchanged']], Output::rows($display));
        static::assertStringEndsWith("3 submission(s) processed (changed: 1, unchanged: 2).\n", $display);
    }

    public function testAnEmptyReportHasNoCounts(): void
    {
        $output = new BufferedOutput();

        CommandReports::renderReport($output, ReconcileReport::of());

        static::assertStringEndsWith("0 submission(s) processed.\n", $output->fetch());
    }
}
