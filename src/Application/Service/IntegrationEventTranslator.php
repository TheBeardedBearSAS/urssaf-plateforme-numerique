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
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionNotReceived;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionOutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploadRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\PaymentStatusChanged;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionAccepted as DomainSubmissionAccepted;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionNotReceived as DomainSubmissionNotReceived;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionOutcomeUnknown as DomainSubmissionOutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionRejected as DomainSubmissionRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploaded as DomainSubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Event\SubmissionUploadRejected as DomainSubmissionUploadRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;

/**
 * Turns the domain events that matter to the host application into public integration events (ADR 0010); the others
 * (the technical failures of an upload: error and invalid_credentials) have none. The event id comes from the
 * {@see IdGenerator} and is stored with the event in the outbox, so every delivery of the same fact carries it.
 */
final readonly class IntegrationEventTranslator
{
    private PollResultTranslator $pollResults;

    public function __construct(private IdGenerator $ids)
    {
        $this->pollResults = new PollResultTranslator($ids);
    }

    /**
     * @return list<IntegrationEvent> the integration events of the domain events, in order
     */
    public function translate(DomainEvent ...$events): array
    {
        $translated = [];
        foreach ($events as $event) {
            $integrationEvent = $this->translateOne($event);
            if ($integrationEvent instanceof IntegrationEvent) {
                $translated[] = $integrationEvent;
            }
        }

        return $translated;
    }

    private function translateOne(DomainEvent $event): ?IntegrationEvent
    {
        return match (true) {
            $event instanceof DomainSubmissionUploaded => $this->uploaded($event),
            $event instanceof DomainSubmissionUploadRejected => $this->uploadRejected($event),
            $event instanceof DomainSubmissionOutcomeUnknown => $this->outcomeUnknown($event),
            $event instanceof DomainSubmissionNotReceived => $this->notReceived($event),
            $event instanceof DomainSubmissionAccepted => $this->pollResults->accepted($event),
            $event instanceof DomainSubmissionRejected => $this->pollResults->rejected($event),
            $event instanceof PaymentStatusChanged => $this->pollResults->paymentStatusChanged($event),
            default => null,
        };
    }

    private function uploaded(DomainSubmissionUploaded $event): SubmissionUploaded
    {
        $identity = $event->identity();

        return new SubmissionUploaded(
            $this->ids->generate(),
            self::instant($event->occurredAt()),
            $identity->tenantId()->value(),
            $identity->id()->value(),
            $identity->externalReference()->value(),
            $identity->kind()->value,
            $identity->declarationType()?->value,
            $identity->fileName()->value(),
            $event->urssafReference()->value(),
        );
    }

    private function uploadRejected(DomainSubmissionUploadRejected $event): SubmissionUploadRejected
    {
        $identity = $event->identity();
        $codes = array_values(array_filter(
            array_map(static fn(SubmissionError $error): ?string => $error->code(), $event->errors()),
            static fn(?string $code): bool => $code !== null,
        ));

        return new SubmissionUploadRejected(
            $this->ids->generate(),
            self::instant($event->occurredAt()),
            $identity->tenantId()->value(),
            $identity->id()->value(),
            $identity->externalReference()->value(),
            $identity->kind()->value,
            $identity->declarationType()?->value,
            $identity->fileName()->value(),
            null,
            $codes,
        );
    }

    private function outcomeUnknown(DomainSubmissionOutcomeUnknown $event): SubmissionOutcomeUnknown
    {
        $identity = $event->identity();

        return new SubmissionOutcomeUnknown(
            $this->ids->generate(),
            self::instant($event->occurredAt()),
            $identity->tenantId()->value(),
            $identity->id()->value(),
            $identity->externalReference()->value(),
            $identity->kind()->value,
            $identity->declarationType()?->value,
            $identity->fileName()->value(),
            null,
        );
    }

    private function notReceived(DomainSubmissionNotReceived $event): SubmissionNotReceived
    {
        $identity = $event->identity();

        return new SubmissionNotReceived(
            $this->ids->generate(),
            self::instant($event->occurredAt()),
            $identity->tenantId()->value(),
            $identity->id()->value(),
            $identity->externalReference()->value(),
            $identity->kind()->value,
            $identity->declarationType()?->value,
            $identity->fileName()->value(),
            null,
        );
    }

    private static function instant(DateTimeImmutable $occurredAt): string
    {
        return Instant::fromDateTime($occurredAt)->format();
    }
}
