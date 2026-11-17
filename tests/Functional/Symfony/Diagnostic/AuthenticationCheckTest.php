<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Diagnostic;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Credentials;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\AuthenticationCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use ValueError;

final class AuthenticationCheckTest extends TestCase
{
    public function testEveryTenantGetsAnAccessToken(): void
    {
        $harness = Harness::boot();

        $results = self::check($harness)->run(CheckContext::withRemoteCalls([TenantId::fromString(TestKernel::TENANT)]));

        static::assertEquals([new CheckResult('authentication', TestKernel::TENANT, CheckStatus::Ok, 'Access token granted by URSSAF.')], $results);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testACachedTokenNeverHidesRefusedCredentials(): void
    {
        $harness = Harness::boot();
        $check = self::check($harness);
        $context = CheckContext::withRemoteCalls([TenantId::fromString(TestKernel::TENANT)]);

        $check->run($context);
        $check->run($context);
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{}');
        $refused = $check->run($context);

        static::assertSame(3, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
        static::assertCount(1, $refused);
        static::assertSame(['authentication', TestKernel::TENANT, CheckStatus::Failure], [$refused[0]->check, $refused[0]->subject, $refused[0]->status]);
        static::assertStringNotContainsString(TestKernel::SECRET, $refused[0]->detail);
    }

    public function testAnUnreachableUrssafIsAFailure(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->failTransport(HttpScript::ROUTE_AUTHENTICATION);

        $results = self::check($harness)->run(CheckContext::withRemoteCalls([TenantId::fromString(TestKernel::TENANT)]));

        static::assertCount(1, $results);
        static::assertSame(CheckStatus::Failure, $results[0]->status);
    }

    public function testATenantWithoutCredentialsIsAFailure(): void
    {
        $harness = Harness::boot();
        $check = new AuthenticationCheck(new InMemoryCredentialsProvider([]), static fn(): OAuth2TokenProvider => self::tokens($harness));

        $results = $check->run(CheckContext::withRemoteCalls([TenantId::fromString('tenant-9')]));

        static::assertCount(1, $results);
        static::assertSame(['authentication', 'tenant-9', CheckStatus::Failure], [$results[0]->check, $results[0]->subject, $results[0]->status]);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testAnUnknownEnvironmentIsAFailure(): void
    {
        $check = new AuthenticationCheck(
            new InMemoryCredentialsProvider(['tenant-1' => Credentials::of('client-1', 'fictitious-secret')]),
            static fn(): OAuth2TokenProvider => throw new ValueError(\sprintf('"staging" is not a valid backing value for enum %s', Environment::class)),
        );

        $results = $check->run(CheckContext::withRemoteCalls([TenantId::fromString('tenant-1')]));

        static::assertCount(1, $results);
        static::assertSame(CheckStatus::Failure, $results[0]->status);
        static::assertStringContainsString('staging', $results[0]->detail);
    }

    public function testNothingIsSentWhenRemoteCallsAreNotAllowed(): void
    {
        $harness = Harness::boot();

        $results = self::check($harness)->run(CheckContext::withoutRemoteCalls([TenantId::fromString(TestKernel::TENANT)]));

        static::assertEquals([new CheckResult('authentication', '', CheckStatus::Skipped, 'Not checked (--skip-remote).')], $results);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    private static function check(Harness $harness): AuthenticationCheck
    {
        return new AuthenticationCheck(
            new InMemoryCredentialsProvider([TestKernel::TENANT => Credentials::of(TestKernel::CLIENT_ID, TestKernel::SECRET)]),
            static fn(): OAuth2TokenProvider => self::tokens($harness),
        );
    }

    private static function tokens(Harness $harness): OAuth2TokenProvider
    {
        return $harness->privateService(ServiceIds::PROBE_TOKEN_PROVIDER, OAuth2TokenProvider::class);
    }
}
