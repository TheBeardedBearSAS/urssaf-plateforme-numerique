<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Application\Service;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionIdentity;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Finds at URSSAF the submissions that may be an upload of unknown outcome (ADR 0003): the matching operation is
 * searched on the platform of the upload (identifications, or declarations of its type), sorted
 * "lastUpdateDate:DESC", by pages of {@see WorkflowPolicy::reconciliationPageSize()} items, up to the policy
 * reconciliationMaxPages, stopping at the first page that is not full. A candidate is a result whose file name equals
 * the uploaded name and whose urssafRef is not held by a submission of the same tenant and kind. That URSSAF echoes
 * the uploaded name verbatim is not published (section 5).
 */
final readonly class ReconciliationSearch
{
    /**
     * Sort key of the searches (shown by the contract examples, section 2.3).
     */
    public const SORT_FIELD = 'lastUpdateDate';

    public function __construct(
        private SubmissionRepository $submissions,
        private PlatformGateway $gateway,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @return list<UrssafReference> the distinct references of the candidates
     *
     * @throws UrssafRequestRejected      when URSSAF refuses the search
     * @throws UrssafAuthenticationFailed when URSSAF refuses the credentials
     * @throws RetryableException         after a temporary failure
     */
    public function candidates(SubmissionIdentity $identity): array
    {
        $size = $this->policy->reconciliationPageSize();
        $candidates = [];
        for ($page = 1; $page <= $this->policy->reconciliationMaxPages(); ++$page) {
            $results = $this->search($identity, SubmissionSearch::of(
                PageRequest::of($page, $size, SortOrder::of(self::SORT_FIELD, SortDirection::Desc)),
                null,
                null,
                null,
                $identity->declarationType(),
            ));
            foreach ($results as [$fileName, $reference]) {
                if (!$this->isCandidate($identity, $fileName, $reference)) {
                    continue;
                }
                $candidates[$reference->value()] = $reference;
            }
            if (\count($results) < $size) {
                break;
            }
        }

        return array_values($candidates);
    }

    /**
     * @phpstan-assert-if-true UrssafReference $reference
     */
    private function isCandidate(SubmissionIdentity $identity, ?string $fileName, ?UrssafReference $reference): bool
    {
        return $fileName === $identity->fileName()->value()
            && $reference instanceof UrssafReference
            && !$this->submissions->isUrssafReferenceKnown($identity->tenantId(), $identity->kind(), $reference);
    }

    /**
     * @return list<array{?string, ?UrssafReference}> the file name and urssafRef of each result
     */
    private function search(SubmissionIdentity $identity, SubmissionSearch $search): array
    {
        if ($identity->kind() === SubmissionKind::Declaration) {
            return array_map(
                static fn(DeclarationSubmission $result): array => [$result->declarationFileName(), $result->urssafRef()],
                $this->gateway->searchDeclarations($identity->tenantId(), $identity->platformId(), $search)->items(),
            );
        }

        return array_map(
            static fn(IdentificationSubmission $result): array => [$result->identificationFileName(), $result->urssafRef()],
            $this->gateway->searchIdentifications($identity->tenantId(), $identity->platformId(), $search)->items(),
        );
    }
}
