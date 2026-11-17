<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatuses;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class SimulatedGatewayTest extends TestCase
{
    public function testTheRealGatewayIsUsedByDefault(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(UrssafHttpGateway::class, $harness->privateService(PlatformGateway::class, PlatformGateway::class));
        static::assertFalse($harness->has(SimulatedPlatformGateway::class));
    }

    public function testFromTheUploadToTheAcceptedFileWithoutNetwork(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true], 'environment' => 'test_api']);
        $urssaf = $harness->get(SimulatedPlatformGateway::class);
        static::assertSame($urssaf, $harness->privateService(PlatformGateway::class, PlatformGateway::class));
        $urssaf->progressThrough(FileStatus::Accepted);
        $tenant = TenantId::fromString(Scenario::TENANT);

        $report = Scenario::uploadedIdentification($harness);
        $harness->clock->modify('+1 hour');
        $harness->get(SyncSubmissionStatusesHandler::class)(SyncSubmissionStatuses::forTenant($tenant, null));

        static::assertSame(UploadOutcome::Uploaded, $report->outcome);
        static::assertSame('simulated-1', $report->urssafReference?->value());
        $view = $harness->get(GetSubmissionHandler::class)(new GetSubmission($tenant, $report->submissionId));
        static::assertSame('accepted', $view->state);
        static::assertSame([Operation::UploadIdentification, Operation::GetIdentification], $urssaf->calls());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testTheSimulatorTargetsTheConfiguredEnvironment(): void
    {
        $urssaf = Harness::boot(['gateway' => ['simulated' => true], 'environment' => 'test_api'])->get(SimulatedPlatformGateway::class);

        $this->expectException(OperationNotSupported::class);

        $urssaf->newsFeed(TenantId::fromString(Scenario::TENANT), PlatformId::fromString(TestKernel::PLATFORM_ID), PageRequest::firstPage());
    }

    public function testTheSimulatorIsRefusedAtRuntimeWhenThePlaceholderResolvesToProduction(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'production';
        $_SERVER['URSSAF_TEST_ENVIRONMENT'] = 'production';
        try {
            $harness = Harness::boot(['gateway' => ['simulated' => true], 'environment' => '%env(URSSAF_TEST_ENVIRONMENT)%']);

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('"gateway.simulated" is meant for tests and cannot be used with the production environment.');

            $harness->get(SimulatedPlatformGateway::class);
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT'], $_SERVER['URSSAF_TEST_ENVIRONMENT']);
        }
    }
}
