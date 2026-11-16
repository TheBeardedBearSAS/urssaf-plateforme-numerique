<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;

/**
 * Payment state of a declaration as reported by the API: the raw code and label plus the known status, if any
 * (an unexpected code never breaks a read).
 */
final readonly class PaymentState
{
    private function __construct(
        private ?string $raw,
        private ?string $label,
        private ?PaymentStatus $status,
    ) {}

    public static function of(?string $raw, ?string $label): self
    {
        return new self($raw, $label, PaymentStatus::fromCode($raw));
    }

    public function raw(): ?string
    {
        return $this->raw;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function status(): ?PaymentStatus
    {
        return $this->status;
    }
}
