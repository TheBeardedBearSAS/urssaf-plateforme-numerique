<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;

/**
 * Counters of a processed identification file ("details" of the API); no unit (ADR 0006).
 */
final readonly class IdentificationDetails
{
    private function __construct(
        private ?DecimalNumber $numberOfDeclaredUser,
        private ?DecimalNumber $numberOfIdentifiedUser,
        private ?DecimalNumber $numberOfError,
    ) {}

    public static function of(
        ?DecimalNumber $numberOfDeclaredUser,
        ?DecimalNumber $numberOfIdentifiedUser,
        ?DecimalNumber $numberOfError,
    ): self {
        return new self($numberOfDeclaredUser, $numberOfIdentifiedUser, $numberOfError);
    }

    public function numberOfDeclaredUser(): ?DecimalNumber
    {
        return $this->numberOfDeclaredUser;
    }

    public function numberOfIdentifiedUser(): ?DecimalNumber
    {
        return $this->numberOfIdentifiedUser;
    }

    public function numberOfError(): ?DecimalNumber
    {
        return $this->numberOfError;
    }
}
