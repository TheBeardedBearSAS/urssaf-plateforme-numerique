<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;

/**
 * Counters and amounts of a processed declaration file ("statistics" of the API); no unit nor currency (ADR 0006).
 */
final readonly class DeclarationStatistics
{
    private function __construct(
        private ?DecimalNumber $numberOfDeclaredUser,
        private ?DecimalNumber $totalRevenue,
        private ?DecimalNumber $debitAmount,
        private ?DecimalNumber $paymentAmount,
        private ?DecimalNumber $numberOfError,
    ) {}

    public static function of(
        ?DecimalNumber $numberOfDeclaredUser,
        ?DecimalNumber $totalRevenue,
        ?DecimalNumber $debitAmount,
        ?DecimalNumber $paymentAmount,
        ?DecimalNumber $numberOfError,
    ): self {
        return new self($numberOfDeclaredUser, $totalRevenue, $debitAmount, $paymentAmount, $numberOfError);
    }

    public function numberOfDeclaredUser(): ?DecimalNumber
    {
        return $this->numberOfDeclaredUser;
    }

    public function totalRevenue(): ?DecimalNumber
    {
        return $this->totalRevenue;
    }

    public function debitAmount(): ?DecimalNumber
    {
        return $this->debitAmount;
    }

    public function paymentAmount(): ?DecimalNumber
    {
        return $this->paymentAmount;
    }

    public function numberOfError(): ?DecimalNumber
    {
        return $this->numberOfError;
    }
}
