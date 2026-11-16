<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\SubmissionAlreadyRecorded;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;

final class ApplicationExceptionsTest extends TestCase
{
    public function testSubmissionAlreadyRecorded(): void
    {
        $exception = SubmissionAlreadyRecorded::withExternalReference(ExternalReference::fromString('ref-1'), SubmissionId::fromString('sub-1'));

        static::assertSame('submission.already_recorded', $exception->violationCode());
        static::assertSame(['external_reference' => 'ref-1', 'submission_id' => 'sub-1'], $exception->params());
        static::assertSame('The external reference "ref-1" is already recorded with another file.', $exception->getMessage());
    }

    public function testRetryableUrssafFailureKeepsTheOriginalFailure(): void
    {
        $original = UrssafUnavailable::dueToTransportError(Operation::UploadDeclaration, RuntimeException::class);

        $exception = RetryableUrssafFailure::causedBy($original);

        static::assertSame($original, $exception->getPrevious());
        static::assertSame(0, $exception->getCode());
        static::assertSame('URSSAF is temporarily unavailable: the submission must be retried later.', $exception->getMessage());
    }
}
