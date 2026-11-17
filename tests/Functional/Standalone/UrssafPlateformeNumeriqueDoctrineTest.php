<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Standalone;

use Http\Mock\Client;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\InMemoryFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Standalone\Support\StandaloneSetup;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\SequentialIdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

/**
 * The builder wired with the Doctrine persistence.
 */
final class UrssafPlateformeNumeriqueDoctrineTest extends TestCase
{
    public function testTheDoctrinePersistenceIsUsedWithItsStatistics(): void
    {
        $entityManager = TestDatabase::entityManager();
        $http = new Client();
        $http->addResponse(new Response(200, [], StandaloneSetup::token()));
        $http->addResponse(new Response(200, [], WireFixtures::load('upload_response')));
        $package = StandaloneSetup::builder($http)
            ->withIdGenerator(new SequentialIdGenerator())
            ->withDoctrine($entityManager)
            ->build();

        $package->uploadIdentification()(UploadIdentification::of(
            TenantId::fromString(StandaloneSetup::TENANT),
            ExternalReference::fromString('host-ref-1'),
            InMemoryFileSource::of(FileName::fromString('DPID_202505_24001_000_20250428150823.xml.gz'), 'fictitious-bytes'),
        ));

        $count = $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM urssaf_pn_submissions');
        static::assertIsNumeric($count);
        static::assertSame(1, (int) $count);
        static::assertSame(1, $package->getWorkflowOverview()(GetWorkflowOverview::allTenants())->tenants()[0]->states['in_process']);
    }
}
