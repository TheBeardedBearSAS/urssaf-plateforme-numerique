<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Testing;

use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\PaymentState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\PageInfo;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ApiTimestamp;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingDates;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Uploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\WithholdingPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use Throwable;

/**
 * An in-memory URSSAF platform for the tests of a host application: it keeps the uploaded files per tenant and
 * platform, and answers the nine operations like the real API would, without any network.
 *
 * Default behaviour (every step can be scripted):
 * - uploads: the file is accepted and gets a new URSSAF reference, `simulated-<n>` (n counts the accepted uploads); the
 *   withholding period is read from the file name (`YYYYMM` or `YYYY-MM`, else the month of the clock);
 * - get and search: each read of a submission returns the next step of its processing status sequence (default
 *   IN_PROCESS then ACCEPTED) and of its payment status sequence (default NOT_INITIATED), the last step is repeated;
 *   searches filter on the type, the keyword (file name) and the withholding period bounds, sort on `period` and
 *   `lastUpdateDate` (any other field keeps the upload order) and paginate (page 1, 20 per page by default); the sort
 *   uses the update instants from before the read, which then moves the instants of the returned page to the clock;
 * - report files: the global report is always listed, the detailed one once the file is accepted; the content of a
 *   report is the one scripted with {@see reportContent()}, else the file is not found; a download is typed
 *   `application/octet-stream` and has no Content-Disposition, as documented for the real API;
 * - news feed: the scripted items, refused like the real test API when the simulator targets {@see Environment::TestApi}.
 *
 * Never use it outside tests: nothing is sent to URSSAF.
 *
 * Implements the nine operations plus the scripting and inspection API, hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class SimulatedPlatformGateway implements PlatformGateway
{
    private const DEFAULT_PAGE_SIZE = 20;
    private const PERIOD_IN_NAME = '/(?<!\d)(\d{4})-?(0[1-9]|1[0-2])(?!\d)/';
    private const UPLOADER_ID = 'simulated-user';
    private const UPLOADER_EMAIL = 'simulated.user@example.test';
    private const STATUS_LABEL = 'Simulated status.';
    private const PAYMENT_LABEL = 'Simulated payment status.';

    /** @var array<string, list<Throwable>> failures to throw, by operation */
    private array $failures = [];

    /** @var list<ApiErrors> errors of the next uploads to refuse */
    private array $uploadRefusals = [];

    /** @var non-empty-list<FileStatus> */
    private array $statusSteps = [FileStatus::InProcess, FileStatus::Accepted];

    /** @var non-empty-list<PaymentStatus> */
    private array $paymentSteps = [PaymentStatus::NotInitiated];

    /** @var list<NewsItem> */
    private array $news = [];

    /** @var array<string, string> content of the report files, by file id */
    private array $reports = [];

    /** @var array<string, SimulatedSubmission> by URSSAF reference, in upload order */
    private array $submissions = [];

    /** @var list<Operation> */
    private array $calls = [];

    private int $uploads = 0;

    public function __construct(
        private readonly Clock $clock,
        private readonly Environment $environment = Environment::Sandbox,
    ) {}

    /**
     * The next call of the operation throws the failure (for example `UrssafUnavailable::withStatus(...)`,
     * `UrssafAuthenticationFailed::withStatus(...)`); failures queued for the same operation are thrown in order.
     */
    public function failNext(Operation $operation, Throwable $failure): void
    {
        $this->failures[$operation->value][] = $failure;
    }

    /**
     * The next upload (identification or declaration) is refused with these URSSAF error codes (HTTP 400); nothing is
     * stored and no reference is consumed.
     */
    public function rejectNextUpload(string $errorCode, string ...$moreErrorCodes): void
    {
        $this->uploadRefusals[] = self::errors($errorCode, ...$moreErrorCodes);
    }

    /**
     * Processing statuses read from the files uploaded from now on: each read returns the next one, the last one is
     * repeated.
     */
    public function progressThrough(FileStatus $first, FileStatus ...$next): void
    {
        $this->statusSteps = array_values([$first, ...$next]);
    }

    /**
     * Payment statuses read from the files uploaded from now on (declarations only): each read returns the next one,
     * the last one is repeated.
     */
    public function progressPaymentThrough(PaymentStatus $first, PaymentStatus ...$next): void
    {
        $this->paymentSteps = array_values([$first, ...$next]);
    }

    /**
     * The items served by the news feed (replaces the previous ones), in the given order.
     */
    public function newsItems(NewsItem ...$items): void
    {
        $this->news = array_values($items);
    }

    /**
     * Content of the report file with this id, as downloaded by the download operations.
     */
    public function reportContent(string $fileId, string $content): void
    {
        $this->reports[$fileId] = $content;
    }

    /**
     * @return list<Operation> every call received, in order (failed ones included)
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function uploadIdentification(TenantId $tenantId, PlatformId $platformId, FileSource $file): UrssafReference
    {
        return $this->upload(Operation::UploadIdentification, $tenantId, $platformId, null, $file);
    }

    public function uploadDeclaration(
        TenantId $tenantId,
        PlatformId $platformId,
        DeclarationType $type,
        FileSource $file,
    ): UrssafReference {
        return $this->upload(Operation::UploadDeclaration, $tenantId, $platformId, $type, $file);
    }

    public function getIdentification(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
    ): IdentificationSubmission {
        $this->receive(Operation::GetIdentification);
        $submission = $this->find(Operation::GetIdentification, $tenantId, $platformId, SubmissionKind::Identification, $reference);
        $this->poll($submission);

        return $this->identification($submission);
    }

    public function searchIdentifications(TenantId $tenantId, PlatformId $platformId, SubmissionSearch $search): Page
    {
        $this->receive(Operation::SearchIdentifications);
        [$items, $info] = $this->searched($tenantId, $platformId, SubmissionKind::Identification, $search);

        return Page::of(array_map($this->identification(...), $items), $info);
    }

    public function downloadIdentificationFile(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
        FileReference $file,
    ): DownloadedFile {
        $this->receive(Operation::DownloadIdentificationFile);
        $this->find(Operation::DownloadIdentificationFile, $tenantId, $platformId, SubmissionKind::Identification, $reference);

        return $this->download(Operation::DownloadIdentificationFile, $file);
    }

    public function getDeclaration(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
    ): DeclarationSubmission {
        $this->receive(Operation::GetDeclaration);
        $submission = $this->find(Operation::GetDeclaration, $tenantId, $platformId, SubmissionKind::Declaration, $reference);
        $this->poll($submission);

        return $this->declaration($submission);
    }

    public function searchDeclarations(TenantId $tenantId, PlatformId $platformId, SubmissionSearch $search): Page
    {
        $this->receive(Operation::SearchDeclarations);
        [$items, $info] = $this->searched($tenantId, $platformId, SubmissionKind::Declaration, $search);

        return Page::of(array_map($this->declaration(...), $items), $info);
    }

    public function downloadDeclarationFile(
        TenantId $tenantId,
        PlatformId $platformId,
        UrssafReference $reference,
        FileReference $file,
    ): DownloadedFile {
        $this->receive(Operation::DownloadDeclarationFile);
        $this->find(Operation::DownloadDeclarationFile, $tenantId, $platformId, SubmissionKind::Declaration, $reference);

        return $this->download(Operation::DownloadDeclarationFile, $file);
    }

    public function newsFeed(TenantId $tenantId, PlatformId $platformId, PageRequest $page): Page
    {
        $this->receive(Operation::GetNewsFeed);
        if (!$this->environment->supportsNewsFeed()) {
            throw OperationNotSupported::newsFeedOnTestApi();
        }
        [$items, $info] = self::slice($this->news, $page);

        return Page::of($items, $info);
    }

    /**
     * A declaration upload carries its type, an identification upload does not.
     */
    private function upload(
        Operation $operation,
        TenantId $tenantId,
        PlatformId $platformId,
        ?DeclarationType $type,
        FileSource $file,
    ): UrssafReference {
        $this->receive($operation);
        $refusal = array_shift($this->uploadRefusals);
        if ($refusal instanceof ApiErrors) {
            throw UrssafRequestRejected::withErrors($operation, 400, $refusal);
        }
        $reference = UrssafReference::fromString(\sprintf('simulated-%d', ++$this->uploads));
        $now = Instant::fromDateTime($this->clock->now());
        $name = $file->name();
        $this->submissions[$reference->value()] = new SimulatedSubmission(
            $tenantId,
            $platformId,
            $type instanceof DeclarationType ? SubmissionKind::Declaration : SubmissionKind::Identification,
            $reference,
            $name,
            $type,
            [self::periodOf($name->value(), $now)],
            $now,
            new SimulatedSequence($this->statusSteps),
            new SimulatedSequence($this->paymentSteps),
        );

        return $reference;
    }

    private function download(Operation $operation, FileReference $file): DownloadedFile
    {
        $content = $this->reports[$file->fileId()] ?? throw UrssafResourceNotFound::withErrors($operation, ApiErrors::none());

        return new DownloadedFile(
            new SimulatedBinaryStream($content),
            'application/octet-stream',
            null,
        );
    }

    private function find(
        Operation $operation,
        TenantId $tenantId,
        PlatformId $platformId,
        SubmissionKind $kind,
        UrssafReference $reference,
    ): SimulatedSubmission {
        $submission = $this->submissions[$reference->value()] ?? null;
        if ($submission === null || !$submission->isOf($tenantId, $platformId, $kind)) {
            throw UrssafResourceNotFound::withErrors($operation, ApiErrors::none());
        }

        return $submission;
    }

    /**
     * The submissions of the page asked for (read once each), and the pagination figures.
     *
     * @return array{list<SimulatedSubmission>, PageInfo}
     */
    private function searched(TenantId $tenantId, PlatformId $platformId, SubmissionKind $kind, SubmissionSearch $search): array
    {
        $found = array_filter(
            $this->submissions,
            static fn(SimulatedSubmission $submission): bool => $submission->isOf($tenantId, $platformId, $kind)
                && self::matches($submission, $search),
        );
        usort($found, static fn(SimulatedSubmission $a, SimulatedSubmission $b): int => self::compare($a, $b, $search->pageRequest()->sorts()));
        [$items, $info] = self::slice($found, $search->pageRequest());
        foreach ($items as $item) {
            $this->poll($item);
        }

        return [$items, $info];
    }

    private static function matches(SimulatedSubmission $submission, SubmissionSearch $search): bool
    {
        $keyword = $search->search();
        $start = $search->startDate();
        $end = $search->endDate();

        return (!$search->type() instanceof DeclarationType || $submission->type === $search->type())
            && ($keyword === null || mb_stripos($submission->fileName->value(), $keyword) !== false)
            && (!$start instanceof CalendarDate || self::anyPeriod($submission, static fn(WithholdingPeriod $period): bool => $period->compareTo(self::monthOf($start)) >= 0))
            && (!$end instanceof CalendarDate || self::anyPeriod($submission, static fn(WithholdingPeriod $period): bool => $period->compareTo(self::monthOf($end)) <= 0));
    }

    /**
     * The withholding month of a date: the day is ignored by the period bounds of the searches.
     */
    private static function monthOf(CalendarDate $date): WithholdingPeriod
    {
        return WithholdingPeriod::fromString(substr($date->format(), 0, 7));
    }

    /**
     * @param callable(WithholdingPeriod): bool $test
     */
    private static function anyPeriod(SimulatedSubmission $submission, callable $test): bool
    {
        foreach ($submission->periods as $period) {
            if ($test(WithholdingPeriod::fromString($period))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SortOrder> $sorts
     */
    private static function compare(SimulatedSubmission $a, SimulatedSubmission $b, array $sorts): int
    {
        foreach ($sorts as $sort) {
            $order = match ($sort->field()) {
                'period' => $a->periods[0] <=> $b->periods[0],
                'lastUpdateDate' => $a->updatedAt()->toDateTimeImmutable() <=> $b->updatedAt()->toDateTimeImmutable(),
                default => 0,
            };
            if ($order !== 0) {
                return $sort->direction() === SortDirection::Desc ? -$order : $order;
            }
        }

        return 0;
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return array{list<T>, PageInfo}
     */
    private static function slice(array $items, PageRequest $request): array
    {
        $page = $request->page() ?? 1;
        $size = $request->size() ?? self::DEFAULT_PAGE_SIZE;

        return [
            \array_slice($items, ($page - 1) * $size, $size),
            PageInfo::of((int) ceil(\count($items) / $size), \count($items), $page, $size),
        ];
    }

    private function poll(SimulatedSubmission $submission): void
    {
        $submission->poll(Instant::fromDateTime($this->clock->now()));
    }

    private function identification(SimulatedSubmission $submission): IdentificationSubmission
    {
        return new IdentificationSubmission(
            $submission->reference->value(),
            $submission->fileName->value(),
            ReportedPeriod::fromWire($submission->periods[0]),
            $submission->reference,
            Uploader::of(self::UPLOADER_ID, self::UPLOADER_EMAIL),
            ProcessingStatus::of($submission->status()->value, self::STATUS_LABEL),
            $this->dates($submission),
            self::reportFiles($submission),
            null,
        );
    }

    private function declaration(SimulatedSubmission $submission): DeclarationSubmission
    {
        return new DeclarationSubmission(
            $submission->reference->value(),
            $submission->fileName->value(),
            $submission->type instanceof DeclarationType ? $submission->type->value : null,
            array_map(ReportedPeriod::fromWire(...), $submission->periods),
            $submission->reference,
            Uploader::of(self::UPLOADER_ID, self::UPLOADER_EMAIL),
            ProcessingStatus::of($submission->status()->value, self::STATUS_LABEL),
            PaymentState::of($submission->paymentStatus()->value, self::PAYMENT_LABEL),
            $this->dates($submission),
            self::reportFiles($submission),
            null,
            null,
        );
    }

    private function dates(SimulatedSubmission $submission): ProcessingDates
    {
        return ProcessingDates::of(
            $submission->updatedAt(),
            self::timestamp($submission->uploadedAt),
            $submission->status()->isFinal() ? self::timestamp($submission->updatedAt()) : null,
        );
    }

    private static function timestamp(Instant $instant): ApiTimestamp
    {
        return ApiTimestamp::of($instant->format(), new DateTimeZone('UTC'));
    }

    private static function reportFiles(SimulatedSubmission $submission): ReportFiles
    {
        $reference = $submission->reference->value();

        return ReportFiles::of(
            FileReference::of($reference . '-global-report', 'global-report'),
            $submission->status() === FileStatus::Accepted ? FileReference::of($reference . '-detailed-report', 'detailed-report') : null,
            null,
            null,
        );
    }

    private static function periodOf(string $fileName, Instant $now): string
    {
        $matches = [];
        if (preg_match(self::PERIOD_IN_NAME, $fileName, $matches) === 1) {
            return $matches[1] . '-' . $matches[2];
        }

        return $now->toDateTimeImmutable()->format('Y-m');
    }

    private function receive(Operation $operation): void
    {
        $this->calls[] = $operation;
        $queue = $this->failures[$operation->value] ?? [];
        $failure = array_shift($queue);
        $this->failures[$operation->value] = $queue;
        if ($failure !== null) {
            throw $failure;
        }
    }

    private static function errors(string ...$codes): ApiErrors
    {
        return ApiErrors::of(...array_map(
            static fn(string $code): ApiError => ApiError::of($code, 'Simulated error.', 'Simulated by the test gateway.'),
            $codes,
        ));
    }
}
