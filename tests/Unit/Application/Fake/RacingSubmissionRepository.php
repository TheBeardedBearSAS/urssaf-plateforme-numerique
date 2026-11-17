<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PollingWindow;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Repository that simulates another process racing the current one, over a real one: the next lookups by external
 * reference miss a record created concurrently, and the next claims are lost (the stored state changed meanwhile).
 *
 * Implements the whole repository port (one method per query) plus its two switches, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class RacingSubmissionRepository implements SubmissionRepository
{
    private int $hiddenLookups = 0;

    private int $lostClaims = 0;

    public function __construct(private readonly SubmissionRepository $inner) {}

    public function hideNextLookups(int $count): self
    {
        $this->hiddenLookups = $count;

        return $this;
    }

    public function loseNextClaims(int $count): self
    {
        $this->lostClaims = $count;

        return $this;
    }

    public function add(Submission $submission): void
    {
        $this->inner->add($submission);
    }

    public function save(Submission $submission): void
    {
        $this->inner->save($submission);
    }

    public function get(TenantId $tenantId, SubmissionId $id): Submission
    {
        return $this->inner->get($tenantId, $id);
    }

    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?Submission
    {
        if ($this->hiddenLookups > 0) {
            --$this->hiddenLookups;

            return null;
        }

        return $this->inner->findByExternalReference($tenantId, $externalReference);
    }

    public function claim(Submission $reclaimed, SubmissionState $expectedStoredState): bool
    {
        if ($this->lostClaims > 0) {
            --$this->lostClaims;

            return false;
        }

        return $this->inner->claim($reclaimed, $expectedStoredState);
    }

    public function dueForPolling(TenantId $tenantId, Instant $now, PollingWindow $window, int $limit): array
    {
        return $this->inner->dueForPolling($tenantId, $now, $window, $limit);
    }

    public function unresolved(TenantId $tenantId, int $limit): array
    {
        return $this->inner->unresolved($tenantId, $limit);
    }

    public function occupiesDeclarationSlot(TenantId $tenantId, DeclarationType $type, SubmissionId $except): bool
    {
        return $this->inner->occupiesDeclarationSlot($tenantId, $type, $except);
    }

    public function isUrssafReferenceKnown(TenantId $tenantId, SubmissionKind $kind, UrssafReference $reference): bool
    {
        return $this->inner->isUrssafReferenceKnown($tenantId, $kind, $reference);
    }

    public function inState(TenantId $tenantId, ?SubmissionState $state, int $limit, int $offset): array
    {
        return $this->inner->inState($tenantId, $state, $limit, $offset);
    }
}
