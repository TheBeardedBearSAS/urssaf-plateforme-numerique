<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Contract\Event;

/**
 * URSSAF rejected the submission after processing (final).
 *
 * Common submission fields: kind ("identification" or "declaration"), declarationType (null for an identification),
 * fileName, urssafRef (null while URSSAF has not given one).
 */
final readonly class SubmissionRejected extends IntegrationEvent
{
    public const TYPE = 'urssaf.pn.submission.rejected';

    /**
     * @param ?string                                                   $status  the raw URSSAF processing status
     * @param list<array{field: string, fileId: string, name: ?string}> $reports the report files (field = wire key)
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
        public ?string $status,
        public array $reports,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
