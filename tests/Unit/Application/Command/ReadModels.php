<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Command;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\PaymentState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\PageInfo;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingDates;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ProcessingStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Uploader;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Live read models answered by the fake gateway: only the file name, the urssafRef and the statuses are set (no
 * uploader: fictitious records).
 */
final class ReadModels
{
    private function __construct() {}

    public static function identification(?string $fileName, ?string $urssafRef, ?string $status): IdentificationSubmission
    {
        return new IdentificationSubmission(
            null,
            $fileName,
            null,
            self::reference($urssafRef),
            Uploader::of(null, null),
            ProcessingStatus::of($status, null),
            ProcessingDates::of(null, null, null),
            ReportFiles::none(),
            null,
        );
    }

    public static function declaration(
        ?string $fileName,
        ?string $urssafRef,
        ?string $status,
        ?string $paymentStatus,
    ): DeclarationSubmission {
        return new DeclarationSubmission(
            null,
            $fileName,
            'DECLARATION',
            [],
            self::reference($urssafRef),
            Uploader::of(null, null),
            ProcessingStatus::of($status, null),
            PaymentState::of($paymentStatus, null),
            ProcessingDates::of(null, null, null),
            ReportFiles::none(),
            null,
            null,
        );
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return Page<T>
     */
    public static function page(array $items): Page
    {
        return Page::of($items, PageInfo::unknown());
    }

    /**
     * @return Page<IdentificationSubmission>
     */
    public static function noIdentifications(): Page
    {
        return self::fullOfOthers(0);
    }

    /**
     * A page of $count identifications that match nothing (file "other.zip").
     *
     * @return Page<IdentificationSubmission>
     */
    public static function fullOfOthers(int $count): Page
    {
        return self::page(array_map(
            static fn(int $number): IdentificationSubmission => self::identification('other.zip', 'other-' . $number, 'IN_PROCESS'),
            $count > 0 ? range(1, $count) : [],
        ));
    }

    private static function reference(?string $urssafRef): ?UrssafReference
    {
        return $urssafRef === null ? null : UrssafReference::fromString($urssafRef);
    }
}
