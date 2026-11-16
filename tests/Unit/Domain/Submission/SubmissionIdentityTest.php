<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Submission;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;

final class SubmissionIdentityTest extends TestCase
{
    public function testDeclaration(): void
    {
        $identity = SubmissionFixtures::identity(SubmissionKind::Declaration, DeclarationType::Regularisation);

        static::assertSame('submission-1', $identity->id()->value());
        static::assertSame('tenant-1', $identity->tenantId()->value());
        static::assertSame('platform-1', $identity->platformId()->value());
        static::assertSame('host-ref-1', $identity->externalReference()->value());
        static::assertSame(SubmissionKind::Declaration, $identity->kind());
        static::assertSame(DeclarationType::Regularisation, $identity->declarationType());
        static::assertSame('file-1.zip', $identity->fileName()->value());
        static::assertSame(SubmissionFixtures::FINGERPRINT, $identity->fingerprint()->value());
    }

    public function testIdentification(): void
    {
        $identity = SubmissionFixtures::identification();

        static::assertSame(SubmissionKind::Identification, $identity->kind());
        static::assertNull($identity->declarationType());
    }

    /**
     * @return iterable<string, array{SubmissionKind, ?DeclarationType, string}>
     */
    public static function provideMismatches(): iterable
    {
        yield 'declaration without type' => [SubmissionKind::Declaration, null, ''];
        yield 'identification with a type' => [SubmissionKind::Identification, DeclarationType::Declaration, 'DECLARATION'];
    }

    #[DataProvider('provideMismatches')]
    public function testDeclarationTypeMismatch(SubmissionKind $kind, ?DeclarationType $type, string $typeParam): void
    {
        try {
            SubmissionFixtures::identity($kind, $type);
            static::fail('The identity must be refused.');
        } catch (InvariantViolation $exception) {
            static::assertSame('submission.declaration_type_mismatch', $exception->violationCode());
            static::assertSame('A declaration requires a declaration type and an identification has none.', $exception->getMessage());
            static::assertSame(['kind' => $kind->value, 'declaration_type' => $typeParam], $exception->params());
        }
    }
}
