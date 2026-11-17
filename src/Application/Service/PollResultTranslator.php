<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Service;

use DateTimeImmutable;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\DeclarationPaymentStatusChanged;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionAccepted;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\PaymentStatusChanged;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionAccepted as DomainSubmissionAccepted;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionRejected as DomainSubmissionRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;

/**
 * Integration events of what a poll read from URSSAF: the processing result (accepted, rejected) and the change of the
 * payment status of a declaration. Used by {@see IntegrationEventTranslator}.
 */
final readonly class PollResultTranslator
{
    public function __construct(private IdGenerator $ids) {}

    public function accepted(DomainSubmissionAccepted $event): SubmissionAccepted
    {
        $identity = $event->identity();

        return new SubmissionAccepted(
            $this->ids->generate(),
            self::instant($event->occurredAt()),
            $identity->tenantId()->value(),
            $identity->id()->value(),
            $identity->externalReference()->value(),
            $identity->kind()->value,
            $identity->declarationType()?->value,
            $identity->fileName()->value(),
            $event->urssafReference()->value(),
            $event->snapshot()->rawStatus(),
            self::reports($event->snapshot()),
        );
    }

    public function rejected(DomainSubmissionRejected $event): SubmissionRejected
    {
        $identity = $event->identity();

        return new SubmissionRejected(
            $this->ids->generate(),
            self::instant($event->occurredAt()),
            $identity->tenantId()->value(),
            $identity->id()->value(),
            $identity->externalReference()->value(),
            $identity->kind()->value,
            $identity->declarationType()?->value,
            $identity->fileName()->value(),
            $event->urssafReference()->value(),
            $event->snapshot()->rawStatus(),
            self::reports($event->snapshot()),
        );
    }

    public function paymentStatusChanged(PaymentStatusChanged $event): DeclarationPaymentStatusChanged
    {
        $identity = $event->identity();

        return new DeclarationPaymentStatusChanged(
            $this->ids->generate(),
            self::instant($event->occurredAt()),
            $identity->tenantId()->value(),
            $identity->id()->value(),
            $identity->externalReference()->value(),
            $identity->kind()->value,
            $identity->declarationType()?->value,
            $identity->fileName()->value(),
            $event->urssafReference()->value(),
            $event->previousRawPaymentStatus(),
            $event->rawPaymentStatus(),
        );
    }

    /**
     * @return list<array{field: string, fileId: string, name: ?string}>
     */
    private static function reports(SubmissionSnapshot $snapshot): array
    {
        $reports = [];
        foreach ($snapshot->reportFiles()->all() as $field => $reference) {
            $reports[] = ['field' => $field, 'fileId' => $reference->fileId(), 'name' => $reference->name()];
        }

        return $reports;
    }

    private static function instant(DateTimeImmutable $occurredAt): string
    {
        return Instant::fromDateTime($occurredAt)->format();
    }
}
