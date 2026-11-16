<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

/**
 * The report files URSSAF attaches to a processed file; each one is optional.
 */
final readonly class ReportFiles
{
    private function __construct(
        private ?FileReference $globalReport,
        private ?FileReference $detailedReport,
        private ?FileReference $usersAnomaliesReport,
        private ?FileReference $affiliatedUsersFile,
    ) {}

    public static function of(
        ?FileReference $globalReport,
        ?FileReference $detailedReport,
        ?FileReference $usersAnomaliesReport,
        ?FileReference $affiliatedUsersFile,
    ): self {
        return new self($globalReport, $detailedReport, $usersAnomaliesReport, $affiliatedUsersFile);
    }

    public static function none(): self
    {
        return new self(null, null, null, null);
    }

    public function globalReport(): ?FileReference
    {
        return $this->globalReport;
    }

    public function detailedReport(): ?FileReference
    {
        return $this->detailedReport;
    }

    public function usersAnomaliesReport(): ?FileReference
    {
        return $this->usersAnomaliesReport;
    }

    public function affiliatedUsersFile(): ?FileReference
    {
        return $this->affiliatedUsersFile;
    }

    /**
     * The present files keyed by their wire field name.
     *
     * @return array<string, FileReference>
     */
    public function all(): array
    {
        return array_filter([
            'globalReportRef' => $this->globalReport,
            'detailedReportRef' => $this->detailedReport,
            'usersAnomaliesReportRef' => $this->usersAnomaliesReport,
            'affiliatedUsersFileRef' => $this->affiliatedUsersFile,
        ]);
    }
}
