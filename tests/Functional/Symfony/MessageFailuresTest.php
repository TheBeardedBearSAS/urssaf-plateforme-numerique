<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Message\SyncTenantSubmissionsMessage;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\MessageFailures;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\MessageHandler\SyncTenantSubmissionsMessageHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\RetryDelay;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;
use Throwable;

final class MessageFailuresTest extends TestCase
{
    private const MESSENGER = ['messenger' => ['enabled' => true]];

    public function testATemporaryFailureIsRecoverableAndKeepsTheRetryDelayRequestedByUrssaf(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);
        Scenario::uploadedIdentification($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_GET_IDENTIFICATION, 429, '{}', ['Retry-After' => '7']);
        $handler = $harness->privateService(SyncTenantSubmissionsMessageHandler::class, SyncTenantSubmissionsMessageHandler::class);

        try {
            $handler(new SyncTenantSubmissionsMessage(Scenario::TENANT));
            static::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $failure) {
            RetryDelay::assertCarries(7000, $failure);
            static::assertSame(0, $failure->getCode());
            static::assertInstanceOf(RetryableUrssafFailure::class, $failure->getPrevious());
            static::assertSame($failure->getPrevious()->getMessage(), $failure->getMessage());
        }
    }

    public function testATemporaryFailureWithoutDelayIsRecoverable(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);
        Scenario::uploadedIdentification($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_GET_IDENTIFICATION, 429, '{}');
        $handler = $harness->privateService(SyncTenantSubmissionsMessageHandler::class, SyncTenantSubmissionsMessageHandler::class);

        $this->expectException(RecoverableMessageHandlingException::class);

        $handler(new SyncTenantSubmissionsMessage(Scenario::TENANT));
    }

    public function testAnInvalidTenantIsUnrecoverable(): void
    {
        $handler = Harness::boot(self::MESSENGER, false, true)->privateService(SyncTenantSubmissionsMessageHandler::class, SyncTenantSubmissionsMessageHandler::class);

        try {
            $handler(new SyncTenantSubmissionsMessage(' '));
            static::fail('Expected an unrecoverable exception.');
        } catch (UnrecoverableMessageHandlingException $failure) {
            static::assertInstanceOf(InvalidValue::class, $failure->getPrevious());
        }
    }

    public function testAnUnknownUploadOutcomeIsUnrecoverable(): void
    {
        $unknown = OutcomeUnknown::afterTransportError(Operation::UploadDeclaration, LogicException::class);

        $failure = self::caught(static function () use ($unknown): void {
            MessageFailures::guard(static function () use ($unknown): never {
                throw $unknown;
            });
        });

        static::assertInstanceOf(UnrecoverableMessageHandlingException::class, $failure);
        static::assertSame($unknown, $failure->getPrevious());
        static::assertSame(0, $failure->getCode());
        static::assertSame($unknown->getMessage(), $failure->getMessage());
    }

    public function testTheRetryDelayComesFromTheLocalRateLimiterToo(): void
    {
        $failure = self::caught(static function (): void {
            MessageFailures::guard(static function (): never {
                throw RateLimitExceeded::forOperation(Operation::GetNewsFeed, 3);
            });
        });

        static::assertInstanceOf(RecoverableMessageHandlingException::class, $failure);
        RetryDelay::assertCarries(3000, $failure);
    }

    public function testThereIsNoRetryDelayForATransportFailure(): void
    {
        $transport = UrssafUnavailable::dueToTransportError(Operation::GetNewsFeed, LogicException::class);

        $failure = self::caught(static function () use ($transport): void {
            MessageFailures::guard(static function () use ($transport): never {
                throw $transport;
            });
        });

        static::assertInstanceOf(RecoverableMessageHandlingException::class, $failure);
        RetryDelay::assertCarries(null, $failure);
        static::assertSame($transport, $failure->getPrevious());
    }

    public function testFailuresOfOtherOriginsAreLeftUntouchedAndResultsAreReturned(): void
    {
        $original = new LogicException('database down');

        $failure = self::caught(static function () use ($original): void {
            MessageFailures::guard(static function () use ($original): never {
                throw $original;
            });
        });

        static::assertSame($original, $failure);
        static::assertSame('done', MessageFailures::guard(static fn(): string => 'done'));
    }

    public function testABusyOperationIsRetriedLater(): void
    {
        $failure = MessageFailures::busy('urssaf_pn.submissions.sync.tenant-1');

        static::assertSame('Operation already running (lock "urssaf_pn.submissions.sync.tenant-1" is held).', $failure->getMessage());
        static::assertSame(0, $failure->getCode());
        static::assertNull($failure->getPrevious());
        RetryDelay::assertCarries(30_000, $failure);
    }

    /**
     * @param callable(): void $operation
     * @param-immediately-invoked-callable $operation
     */
    private static function caught(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $failure) {
            return $failure;
        }

        static::fail('Nothing was thrown.');
    }
}
