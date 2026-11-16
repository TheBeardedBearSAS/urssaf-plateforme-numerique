<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Dto;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\StateCount;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * Submissions of one tenant by state. Every state is listed, in workflow order, with 0 when there is no submission.
 */
final readonly class TenantOverview
{
    /**
     * @param array<string, int> $states submissions by SubmissionState value
     */
    private function __construct(public string $tenantId, public array $states) {}

    /**
     * @param list<StateCount> $counts the counts of the tenant (the counts of other tenants are ignored)
     */
    public static function of(TenantId $tenantId, array $counts): self
    {
        $states = array_fill_keys(array_map(static fn(SubmissionState $state): string => $state->value, SubmissionState::cases()), 0);
        foreach ($counts as $count) {
            if (!$count->tenantId->equals($tenantId) || !\array_key_exists($count->state, $states)) {
                continue;
            }
            $states[$count->state] = $count->count;
        }

        return new self($tenantId->value(), $states);
    }

    /**
     * Submissions that wait for a manual action: "outcome_unknown" (to reconcile or to resolve), "invalid_credentials"
     * and "error" (to upload again). Counts every "error", "invalid_credentials" and "outcome_unknown" record, including
     * the transient ones still being retried or reconciled: the statistics port has no attempts nor age. This is a
     * simplification of the package.
     */
    public function needingAttention(): int
    {
        return $this->states[SubmissionState::OutcomeUnknown->value]
            + $this->states[SubmissionState::InvalidCredentials->value]
            + $this->states[SubmissionState::Error->value];
    }

    /**
     * @return array{tenant_id: string, states: array<string, int>, needing_attention: int}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'states' => $this->states,
            'needing_attention' => $this->needingAttention(),
        ];
    }
}
