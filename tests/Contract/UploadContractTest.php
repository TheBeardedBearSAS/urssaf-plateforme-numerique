<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\ReconciliationSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;

/**
 * Opt-in upload to the real-exchange sandbox: URSSAF_SANDBOX_UPLOAD=1 on top of the sandbox variables. It sends a
 * fictitious file whose processing is expected to be rejected asynchronously.
 */
#[Group('contract')]
final class UploadContractTest extends TestCase
{
    private const SEARCH_ATTEMPTS = 6;
    private const SEARCH_PAUSE_SECONDS = 5;

    public function testUploadedIdentificationIsFoundByItsFileNameAndHasAStatus(): void
    {
        if (SandboxGateway::env('URSSAF_SANDBOX_UPLOAD') !== '1') {
            static::markTestSkipped('Set URSSAF_SANDBOX_UPLOAD=1 to upload a fictitious identification file to the sandbox.');
        }

        $sandbox = ContractEnvironment::sandbox();
        $platformId = ContractEnvironment::platform($sandbox);
        $file = SandboxData::identificationFile();
        $name = $file->name()->value();

        $reference = $sandbox->gateway->uploadIdentification($sandbox->tenantId, $platformId, $file);

        // ADR 0003 assumption: the search lists the submission with the uploaded name verbatim.
        $found = null;
        for ($attempt = 1; $attempt <= self::SEARCH_ATTEMPTS && !$found instanceof IdentificationSubmission; ++$attempt) {
            $found = $this->findByFileName($sandbox, $platformId, $name);
            if (!$found instanceof IdentificationSubmission && $attempt < self::SEARCH_ATTEMPTS) {
                sleep(self::SEARCH_PAUSE_SECONDS);
            }
        }

        static::assertInstanceOf(
            IdentificationSubmission::class,
            $found,
            \sprintf('No identification named "%s" found after the upload (ADR 0003 assumption).', $name),
        );
        static::assertSame($reference->value(), $found->urssafRef()?->value());

        $submission = $sandbox->gateway->getIdentification($sandbox->tenantId, $platformId, $reference);

        static::assertContains(
            $submission->processingStatus()->status(),
            [FileStatus::InProcess, FileStatus::Accepted, FileStatus::Rejected],
            \sprintf('Unexpected processing status "%s".', $submission->processingStatus()->raw() ?? 'null'),
        );
        static::assertInstanceOf(Instant::class, $submission->dates()->lastUpdateDate());
    }

    /** Mirrors ReconciliationSearch (ADR 0003): no keyword, newest first, largest page. */
    private function findByFileName(SandboxGateway $sandbox, PlatformId $platformId, string $name): ?IdentificationSubmission
    {
        $page = $sandbox->gateway->searchIdentifications(
            $sandbox->tenantId,
            $platformId,
            SubmissionSearch::of(
                PageRequest::of(1, PageRequest::MAX_SIZE, SortOrder::of(ReconciliationSearch::SORT_FIELD, SortDirection::Desc)),
                null,
                null,
                null,
                null,
            ),
        );

        foreach ($page->items() as $item) {
            if ($item->identificationFileName() === $name) {
                return $item;
            }
        }

        return null;
    }
}
