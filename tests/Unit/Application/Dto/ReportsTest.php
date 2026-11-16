<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReconcileReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\RelayReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReportItem;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SyncReport;

final class ReportsTest extends TestCase
{
    public function testASyncReportKeepsTheItemsInOrder(): void
    {
        $first = new ReportItem('sub-1', 'changed');
        $second = new ReportItem('sub-2', 'unchanged');
        $third = new ReportItem('sub-3', 'changed');

        $report = SyncReport::of(...['first' => $first, 'second' => $second])->with($third);

        static::assertSame([$first, $second, $third], $report->items());
        static::assertSame(3, $report->total());
        static::assertSame(2, $report->count('changed'));
        static::assertSame(1, $report->count('unchanged'));
        static::assertSame(0, $report->count('not_found'));
        static::assertSame('sub-1', $first->submissionId);
        static::assertSame('changed', $first->outcome);
    }

    public function testAReconcileReportKeepsTheItemsInOrder(): void
    {
        $first = new ReportItem('sub-1', 'adopted');
        $second = new ReportItem('sub-2', 'unmatched');
        $third = new ReportItem('sub-3', 'adopted');

        $report = ReconcileReport::of(...['first' => $first, 'second' => $second])->with($third);

        static::assertSame([$first, $second, $third], $report->items());
        static::assertSame(3, $report->total());
        static::assertSame(2, $report->count('adopted'));
        static::assertSame(1, $report->count('unmatched'));
        static::assertSame(0, $report->count('ambiguous'));
    }

    public function testReportsAreImmutable(): void
    {
        $item = new ReportItem('sub-1', 'changed');
        $sync = SyncReport::of();
        $reconcile = ReconcileReport::of();

        $syncWith = $sync->with($item);
        $reconcileWith = $reconcile->with($item);

        static::assertSame([], $sync->items());
        static::assertSame([$item], $syncWith->items());
        static::assertSame([], $reconcile->items());
        static::assertSame([$item], $reconcileWith->items());
    }

    public function testReportsAreMerged(): void
    {
        $first = new ReportItem('sub-1', 'changed');
        $second = new ReportItem('sub-2', 'unchanged');
        $third = new ReportItem('sub-3', 'not_found');
        $fourth = new ReportItem('sub-4', 'changed');

        $sync = SyncReport::of($first, $second)->merge(SyncReport::of($third, $fourth));
        $reconcile = ReconcileReport::of($first, $second)->merge(ReconcileReport::of($third, $fourth));
        $left = SyncReport::of($first);
        $left->merge(SyncReport::of($second));

        static::assertSame([$first, $second, $third, $fourth], $sync->items());
        static::assertSame([$first, $second, $third, $fourth], $reconcile->items());
        static::assertSame([$first], $left->items());
        static::assertSame(0, SyncReport::of()->total());
        static::assertSame(0, ReconcileReport::of()->total());
    }

    public function testTheRelayReport(): void
    {
        $report = new RelayReport(3, 2, 1);

        static::assertSame([3, 2, 1], [$report->dispatched, $report->failed, $report->deferred]);
    }
}
