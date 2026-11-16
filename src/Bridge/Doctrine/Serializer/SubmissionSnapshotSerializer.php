<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;

/**
 * Explicit JSON shape of a {@see SubmissionSnapshot}: the raw statuses, the raw periods (parsed again on read, as on
 * the wire), the report file references, the counters as canonical decimal strings (no float, ADR 0006) keyed by
 * their wire names, and the "lastUpdateDate" as an ISO 8601 UTC instant. Never the uploader (ADR 0009).
 *
 * @phpstan-type FileData array{fileId: string, name: string|null}
 * @phpstan-type ReportFilesData array{globalReport: FileData|null, detailedReport: FileData|null, usersAnomaliesReport: FileData|null, affiliatedUsersFile: FileData|null}
 * @phpstan-type SnapshotData array{rawStatus: string|null, rawPaymentStatus: string|null, periods: list<string>, reportFiles: ReportFilesData, counters: array<string, string>, lastUpdateDate: string|null}
 */
final class SubmissionSnapshotSerializer
{
    /**
     * @return SnapshotData
     */
    public static function toArray(SubmissionSnapshot $snapshot): array
    {
        $files = $snapshot->reportFiles();

        return [
            'rawStatus' => $snapshot->rawStatus(),
            'rawPaymentStatus' => $snapshot->rawPaymentStatus(),
            'periods' => array_map(static fn(ReportedPeriod $period): string => $period->raw(), $snapshot->periods()),
            'reportFiles' => [
                'globalReport' => self::file($files->globalReport()),
                'detailedReport' => self::file($files->detailedReport()),
                'usersAnomaliesReport' => self::file($files->usersAnomaliesReport()),
                'affiliatedUsersFile' => self::file($files->affiliatedUsersFile()),
            ],
            'counters' => array_map(static fn(DecimalNumber $counter): string => $counter->toString(), $snapshot->counters()),
            'lastUpdateDate' => $snapshot->lastUpdateDate()?->format(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape SnapshotData
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): SubmissionSnapshot
    {
        /** @var SnapshotData $data */
        $files = $data['reportFiles'];
        $counters = [];
        foreach ($data['counters'] as $name => $counter) {
            $counters[$name] = DecimalNumber::fromString($counter);
        }
        $lastUpdateDate = $data['lastUpdateDate'];

        return new SubmissionSnapshot(
            $data['rawStatus'],
            $data['rawPaymentStatus'],
            array_map(ReportedPeriod::fromWire(...), $data['periods']),
            ReportFiles::of(
                self::reference($files['globalReport']),
                self::reference($files['detailedReport']),
                self::reference($files['usersAnomaliesReport']),
                self::reference($files['affiliatedUsersFile']),
            ),
            $counters,
            $lastUpdateDate === null ? null : Instant::fromIso8601($lastUpdateDate),
        );
    }

    /**
     * @return FileData|null
     */
    private static function file(?FileReference $file): ?array
    {
        return $file instanceof FileReference ? ['fileId' => $file->fileId(), 'name' => $file->name()] : null;
    }

    /**
     * @param FileData|null $data
     *
     * @throws InvalidValue
     */
    private static function reference(?array $data): ?FileReference
    {
        return $data === null ? null : FileReference::of($data['fileId'], $data['name']);
    }
}
