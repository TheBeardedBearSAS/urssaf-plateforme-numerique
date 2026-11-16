<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;

/**
 * What a submission is, fixed at its creation (ADR 0004): local id, owner, host reference and the uploaded file.
 */
final readonly class SubmissionIdentity
{
    /**
     * Public constructor (more than five parameters, as the reference does for its wide value objects).
     *
     * @throws InvariantViolation "submission.declaration_type_mismatch" when a declaration has no type or an
     *                            identification has one
     */
    public function __construct(
        private SubmissionId $id,
        private TenantId $tenantId,
        private PlatformId $platformId,
        private ExternalReference $externalReference,
        private SubmissionKind $kind,
        private ?DeclarationType $declarationType,
        private FileName $fileName,
        private ContentFingerprint $fingerprint,
    ) {
        $isDeclaration = $kind === SubmissionKind::Declaration;
        if ($isDeclaration !== $declarationType instanceof DeclarationType) {
            throw InvariantViolation::because(
                'submission.declaration_type_mismatch',
                'A declaration requires a declaration type and an identification has none.',
                ['kind' => $kind->value, 'declaration_type' => $declarationType->value ?? ''],
            );
        }
    }

    public function id(): SubmissionId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function platformId(): PlatformId
    {
        return $this->platformId;
    }

    /**
     * The reference of the record in the host application.
     */
    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    public function kind(): SubmissionKind
    {
        return $this->kind;
    }

    /**
     * Non-null exactly for declarations.
     */
    public function declarationType(): ?DeclarationType
    {
        return $this->declarationType;
    }

    public function fileName(): FileName
    {
        return $this->fileName;
    }

    public function fingerprint(): ContentFingerprint
    {
        return $this->fingerprint;
    }
}
