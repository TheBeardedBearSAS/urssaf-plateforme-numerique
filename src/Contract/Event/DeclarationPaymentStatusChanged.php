<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Contract\Event;

/**
 * A poll observed a new raw paymentStatus of the declaration.
 *
 * Common submission fields: kind ("identification" or "declaration"), declarationType (null for an identification),
 * fileName, urssafRef (null while URSSAF has not given one).
 */
final readonly class DeclarationPaymentStatusChanged extends IntegrationEvent
{
    public const TYPE = 'urssaf.pn.declaration.payment_status_changed';

    /**
     * @param ?string $previousPaymentStatus null when no payment status was known before
     */
    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $kind,
        public ?string $declarationType,
        public string $fileName,
        public ?string $urssafRef,
        public ?string $previousPaymentStatus,
        public ?string $paymentStatus,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
