<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;

/**
 * What is known of a submission at URSSAF: its reference and what the last poll read, verbatim (null while never
 * polled). Instants ISO 8601 UTC.
 */
final readonly class UrssafTrackingView
{
    /**
     * @param ?string $urssafRef        null until URSSAF holds the file
     * @param ?string $rawStatus        the "status" of the last poll
     * @param ?string $rawPaymentStatus the "paymentStatus" of the last poll (declarations)
     * @param ?string $lastPolledAt     null while never polled
     */
    private function __construct(
        public ?string $urssafRef,
        public ?string $rawStatus,
        public ?string $rawPaymentStatus,
        public ?string $lastPolledAt,
    ) {}

    public static function fromSubmission(Submission $submission): self
    {
        $snapshot = $submission->snapshot();

        return new self(
            $submission->urssafReference()?->value(),
            $snapshot?->rawStatus(),
            $snapshot?->rawPaymentStatus(),
            $submission->lastPolledAt()?->format(),
        );
    }
}
