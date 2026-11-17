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
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SubmissionView;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CommandViews;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;

final class CommandViewsTest extends TestCase
{
    public function testTheDataKeepsTheTypesOfTheView(): void
    {
        $view = SubmissionView::fromSubmission((new Harness())->identification(1, SubmissionState::UploadRejected));

        static::assertSame([
            'submission_id' => $view->submissionId,
            'tenant_id' => 'tenant-1',
            'platform_id' => 'platform-1',
            'external_reference' => 'ref-1',
            'kind' => 'identification',
            'declaration_type' => null,
            'file_name' => 'file-1.zip',
            'state' => 'upload_rejected',
            'attempts' => 1,
            'urssaf_ref' => null,
            'status' => null,
            'payment_status' => null,
            'last_polled_at' => null,
            'created_at' => '2026-01-15T10:00:00Z',
            'updated_at' => '2026-01-15T10:00:00Z',
            'error_codes' => 'ERR_X',
        ], CommandViews::data($view));
    }

    public function testTheRowsAreTextsWithADashForWhatIsUnknown(): void
    {
        $view = SubmissionView::fromSubmission((new Harness())->identification(1, SubmissionState::InProcess));

        $rows = CommandViews::rows($view);

        static::assertContains(['attempts', '1'], $rows);
        static::assertContains(['declaration_type', '-'], $rows);
        static::assertContains(['error_codes', '-'], $rows);
        static::assertContains(['urssaf_ref', 'urssaf-1'], $rows);
        static::assertCount(16, $rows);
    }
}
