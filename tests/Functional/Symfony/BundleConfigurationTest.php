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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Psr18Client;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\BundleConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\UrssafPlateformeNumeriqueBundle;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Platform\InMemoryPlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\Psr6TokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

final class BundleConfigurationTest extends TestCase
{
    public function testTheDriverDefaultsToDoctrineWhenTheOrmIsInstalled(): void
    {
        static::assertSame('doctrine', BundleConfiguration::defaultDriver());
    }

    public function testTheMinimalConfigurationCompiles(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(UploadIdentificationHandler::class, $harness->get(UploadIdentificationHandler::class));
        static::assertInstanceOf(NoopRateLimiter::class, $harness->privateService(RateLimiter::class, RateLimiter::class));
        static::assertInstanceOf(Psr18Client::class, $harness->privateService(ServiceIds::PSR18_CLIENT, Psr18Client::class));
        static::assertInstanceOf(Psr6TokenStore::class, $harness->privateService(TokenStore::class, TokenStore::class));
    }

    public function testTheMinimalConfigurationUsesTheDocumentedDefaults(): void
    {
        $harness = Harness::boot();
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);
        $configuration = $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);

        static::assertSame(10, $policy->pollingWindow()->statusPollInterval()->i);
        static::assertSame(6, $policy->pollingWindow()->paymentPollInterval()->h);
        static::assertSame(1, $policy->pollingWindow()->maxPollingAge()->y);
        static::assertSame(5, $policy->maxUploadAttempts());
        static::assertSame(1, $policy->staleUploadAfter()->h);
        static::assertSame(1, $policy->manualReviewAfter()->d);
        static::assertSame(1, $policy->reconciliationMaxPages());
        static::assertTrue($policy->concurrentDeclarationGuard());
        static::assertSame(30, $policy->outboxRetention()->d);
        static::assertSame(100, $policy->pageSize(null));
        static::assertSame(1000, $policy->pageSize(5000));
        static::assertSame(Environment::Sandbox, $configuration->environment());
        static::assertSame('https://api-edi.urssaf.fr', $configuration->baseUri());
        static::assertSame('FR', $configuration->acceptLanguage());
        static::assertSame('Europe/Paris', $configuration->apiTimeZone()->getName());
        static::assertNull($configuration->requestIdHeader());
        static::assertSame(60, $configuration->tokenSafetyMarginSeconds());
        static::assertSame(ApiConfiguration::DEFAULT_USER_AGENT, $configuration->userAgent());
        static::assertSame([TestKernel::TENANT], $harness->kernel->getContainer()->getParameter(UrssafPlateformeNumeriqueBundle::PARAMETER_TENANTS));
    }

    public function testOnlyTheHandlersArePublic(): void
    {
        $harness = Harness::boot();

        static::assertTrue($harness->has(UploadIdentificationHandler::class));
        static::assertFalse($harness->has(WorkflowPolicy::class));
        static::assertFalse($harness->has(Clock::class));
        static::assertTrue($harness->hasPrivate(Clock::class));
    }

    public function testTheCredentialsAndThePlatformsAreBuiltFromTheConfiguration(): void
    {
        $harness = Harness::boot();
        $tenant = TenantId::fromString(TestKernel::TENANT);

        $credentials = $harness->privateService(CredentialsProvider::class, CredentialsProvider::class);
        $platforms = $harness->privateService(PlatformResolver::class, PlatformResolver::class);

        static::assertInstanceOf(InMemoryCredentialsProvider::class, $credentials);
        static::assertInstanceOf(InMemoryPlatformResolver::class, $platforms);
        static::assertSame(TestKernel::CLIENT_ID, $credentials->forTenant($tenant)->clientId());
        static::assertSame(TestKernel::SECRET, $credentials->forTenant($tenant)->clientSecret());
        static::assertSame(TestKernel::PLATFORM_ID, $platforms->forTenant($tenant)->value());
    }

    public function testTheTokenStoreCanBeProvidedByTheApplication(): void
    {
        $harness = Harness::boot(['token_store' => ['service' => 'test.token_store']]);

        Scenario::uploadedIdentification($harness);
        Scenario::uploadedIdentification($harness, Scenario::TENANT, 'submission-2');

        static::assertSame($harness->privateService('test.token_store', TokenStore::class), $harness->privateService(TokenStore::class, TokenStore::class));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_UPLOAD_IDENTIFICATION));
    }

    public function testTheLimitsOfTheConfigurationAreAccepted(): void
    {
        $harness = Harness::boot([
            'accept_language' => 'FR',
            'token_safety_margin' => 0,
            'workflow' => ['max_upload_attempts' => 1, 'reconciliation_max_pages' => 1, 'default_page_size' => 1, 'max_page_size' => 1, 'stale_upload_after' => 'PT1S'],
        ]);
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);

        static::assertSame(0, $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class)->tokenSafetyMarginSeconds());
        static::assertSame('FR', $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class)->acceptLanguage());
        static::assertSame(1, $policy->maxUploadAttempts());
        static::assertSame(1, $policy->pageSize(5));
        static::assertSame(1, $policy->staleUploadAfter()->s);
    }

    public function testTenantsAreStringsEvenWhenTheyLookLikeNumbers(): void
    {
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        $harness = Harness::boot(['credentials' => ['42' => ['client_id' => 'client-42', 'client_secret' => 'secret-42', 'platform_id' => '4300027']]]);

        static::assertSame([TestKernel::TENANT, '42'], $harness->kernel->getContainer()->getParameter(UrssafPlateformeNumeriqueBundle::PARAMETER_TENANTS));
    }

    public function testTheApplicationProvidesTheCredentialsThePlatformsAndTheRepository(): void
    {
        $harness = Harness::boot([
            'credentials' => null,
            'credentials_provider' => 'test.credentials',
            'platform_resolver' => 'test.platforms',
            'tenants' => ['tenant-9'],
            'persistence' => ['driver' => 'custom', 'repository' => 'test.submissions'],
        ]);

        static::assertSame($harness->privateService('test.credentials', CredentialsProvider::class), $harness->privateService(CredentialsProvider::class, CredentialsProvider::class));
        static::assertSame($harness->privateService('test.platforms', PlatformResolver::class), $harness->privateService(PlatformResolver::class, PlatformResolver::class));
        static::assertSame($harness->privateService('test.submissions', SubmissionRepository::class), $harness->privateService(SubmissionRepository::class, SubmissionRepository::class));
        static::assertInstanceOf(ImmediateTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
        static::assertSame(['tenant-9'], $harness->kernel->getContainer()->getParameter(UrssafPlateformeNumeriqueBundle::PARAMETER_TENANTS));
    }

    public function testTheFullConfigurationCompiles(): void
    {
        $harness = Harness::boot([
            'environment' => 'production',
            'base_uri' => 'https://proxy.example.test/',
            'accept_language' => 'EN',
            'api_timezone' => 'America/Martinique',
            'request_id_header' => 'X-Request-Id',
            'token_safety_margin' => 120,
            'http_client' => null,
            'http_timeout' => 5.5,
            'credentials' => [
                // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
                'tenant-2' => ['client_id' => 'client-2', 'client_secret' => 'secret-2', 'platform_id' => '4300027'],
            ],
            'tenants' => ['tenant-2'],
            'token_store' => ['cache_pool' => 'cache.app'],
            'rate_limiter' => ['enabled' => true, 'max_calls' => 2, 'window_seconds' => 30],
            'workflow' => [
                'status_poll_interval' => 'PT5M',
                'payment_poll_interval' => 'PT2H',
                'max_polling_age' => 'P6M',
                'max_upload_attempts' => 3,
                'stale_upload_after' => 'PT30M',
                'manual_review_after' => 'P2D',
                'reconciliation_max_pages' => 4,
                'concurrent_declaration_guard' => false,
                'default_page_size' => 20,
                'max_page_size' => 50,
            ],
            'outbox' => ['retention' => 'P7D'],
            'lock' => ['enabled' => false, 'factory' => 'lock.factory', 'ttl' => 120.5],
            'persistence' => ['driver' => 'in_memory', 'entity_manager' => 'unused'],
        ]);

        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);
        $configuration = $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);
        static::assertSame(5, $policy->pollingWindow()->statusPollInterval()->i);
        static::assertSame(2, $policy->pollingWindow()->paymentPollInterval()->h);
        static::assertSame(6, $policy->pollingWindow()->maxPollingAge()->m);
        static::assertSame(3, $policy->maxUploadAttempts());
        static::assertSame(30, $policy->staleUploadAfter()->i);
        static::assertSame(2, $policy->manualReviewAfter()->d);
        static::assertSame(4, $policy->reconciliationMaxPages());
        static::assertFalse($policy->concurrentDeclarationGuard());
        static::assertSame(7, $policy->outboxRetention()->d);
        static::assertSame(20, $policy->pageSize(null));
        static::assertSame(50, $policy->pageSize(500));
        static::assertInstanceOf(SlidingWindowRateLimiter::class, $harness->privateService(RateLimiter::class, RateLimiter::class));
        static::assertSame(['tenant-2'], $harness->kernel->getContainer()->getParameter(UrssafPlateformeNumeriqueBundle::PARAMETER_TENANTS));
        static::assertSame(Environment::Production, $configuration->environment());
        static::assertSame('https://proxy.example.test', $configuration->baseUri());
        static::assertSame('EN', $configuration->acceptLanguage());
        static::assertSame('America/Martinique', $configuration->apiTimeZone()->getName());
        static::assertSame('X-Request-Id', $configuration->requestIdHeader());
        static::assertSame(120, $configuration->tokenSafetyMarginSeconds());
    }
}
