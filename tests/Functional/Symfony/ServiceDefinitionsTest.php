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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\EventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ClockFactory;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event\Psr14EventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemorySubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\Psr6TokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;

/**
 * The definitions of the infrastructure, persistence, locks and schedule, as written by the registrars (nothing resolved
 * nor removed).
 */
final class ServiceDefinitionsTest extends TestCase
{
    public function testTheGenericInfrastructure(): void
    {
        $container = (new TestKernel())->definitions();

        $clock = $container->getDefinition(Clock::class);
        static::assertSame([ClockFactory::class, 'create'], $clock->getFactory());
        static::assertEquals([new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $clock->getArguments());
        $ids = $container->getDefinition(IdGenerator::class);
        static::assertSame(RandomUuidGenerator::class, $ids->getClass());
        static::assertEquals([new Definition(SecureRandomBytes::class)], $ids->getArguments());
        $events = $container->getDefinition(EventPublisher::class);
        static::assertSame(Psr14EventPublisher::class, $events->getClass());
        static::assertEquals([new Reference('event_dispatcher')], $events->getArguments());
        $tokens = $container->getDefinition(TokenStore::class);
        static::assertSame(Psr6TokenStore::class, $tokens->getClass());
        static::assertEquals([new Reference('cache.app')], $tokens->getArguments());
    }

    public function testTheTokenStoreUsesTheConfiguredCachePool(): void
    {
        $tokens = (new TestKernel(['token_store' => ['cache_pool' => 'cache.system']]))->definitions()->getDefinition(TokenStore::class);

        static::assertEquals([new Reference('cache.system')], $tokens->getArguments());
    }

    public function testTheRateLimiterUsesTheConfiguredQuota(): void
    {
        $defaults = (new TestKernel(['rate_limiter' => ['enabled' => true]]))->definitions()->getDefinition(RateLimiter::class);
        $configured = (new TestKernel(['rate_limiter' => ['enabled' => true, 'max_calls' => 1, 'window_seconds' => 1]]))->definitions()->getDefinition(RateLimiter::class);

        static::assertSame(SlidingWindowRateLimiter::class, $defaults->getClass());
        static::assertEquals([new Reference(Clock::class), 60, 60], $defaults->getArguments());
        static::assertEquals([new Reference(Clock::class), 1, 1], $configured->getArguments());
    }

    public function testOnlyTheHandlersAreDeclaredPublic(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertTrue($container->getDefinition(UploadIdentificationHandler::class)->isPublic());
        static::assertFalse($container->getDefinition(WorkflowPolicy::class)->isPublic());
        static::assertFalse($container->getDefinition(ServiceIds::JSON_CLIENT)->isPublic());
        static::assertFalse($container->getDefinition(PlatformGateway::class)->isPublic());
    }

    public function testTheServicesOfTheApplicationAreAliasedInsteadOfDuplicated(): void
    {
        $container = (new TestKernel([
            'credentials' => null,
            'credentials_provider' => 'test.credentials',
            'platform_resolver' => 'test.platforms',
            'token_store' => ['service' => 'test.token_store'],
            'persistence' => ['driver' => 'custom', 'repository' => 'my.submissions', 'statistics' => 'my.statistics', 'transaction_manager' => 'my.transactions'],
        ]))->definitions();

        foreach ([
            CredentialsProvider::class => 'test.credentials',
            PlatformResolver::class => 'test.platforms',
            TokenStore::class => 'test.token_store',
            SubmissionRepository::class => 'my.submissions',
            WorkflowStatistics::class => 'my.statistics',
            TransactionManager::class => 'my.transactions',
        ] as $id => $target) {
            static::assertSame($target, (string) $container->getAlias($id), $id);
            static::assertFalse($container->hasDefinition($id), $id);
        }
    }

    public function testTheCustomDriverKeepsTheImmediateTransactionsAndMayHaveNoStatistics(): void
    {
        $container = (new TestKernel(['persistence' => ['driver' => 'custom', 'repository' => 'my.submissions']]))->definitions();

        static::assertSame(ImmediateTransactionManager::class, $container->getDefinition(TransactionManager::class)->getClass());
        static::assertFalse($container->has(WorkflowStatistics::class));
    }

    public function testTheInMemoryDriver(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertSame(InMemorySubmissionRepository::class, $container->getDefinition(SubmissionRepository::class)->getClass());
        static::assertSame(ImmediateTransactionManager::class, $container->getDefinition(TransactionManager::class)->getClass());
        $statistics = $container->getDefinition(WorkflowStatistics::class);
        static::assertSame(InMemoryWorkflowStatistics::class, $statistics->getClass());
        static::assertEquals([new Reference(SubmissionRepository::class)], $statistics->getArguments());
    }

    public function testTheLocksUseTheConfiguredTtl(): void
    {
        $defaults = (new TestKernel())->definitions()->getDefinition(OperationLocks::class);
        $configured = (new TestKernel(['lock' => ['enabled' => true, 'factory' => 'my.lock_factory', 'ttl' => 1.0]]))->definitions()->getDefinition(OperationLocks::class);

        static::assertEquals([new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE), 3600.0], $defaults->getArguments());
        static::assertEquals([new Reference('my.lock_factory'), 1.0], $configured->getArguments());
    }

    public function testTheScheduleRunsTheConfiguredTenantsAtTheConfiguredFrequencies(): void
    {
        $defaults = (new TestKernel(['scheduler' => ['enabled' => true]]))->definitions()->getDefinition(UrssafScheduleProvider::class);
        $configured = (new TestKernel(['scheduler' => ['enabled' => true, 'sync_frequency' => 'PT1H', 'reconcile_frequency' => null]]))->definitions()->getDefinition(UrssafScheduleProvider::class);

        static::assertSame(['%urssaf_plateforme_numerique.tenants%', 'PT10M', 'PT15M'], $defaults->getArguments());
        static::assertSame(['%urssaf_plateforme_numerique.tenants%', 'PT1H', null], $configured->getArguments());
        static::assertSame(['scheduler.schedule_provider' => [['name' => 'urssaf_pn']]], $defaults->getTags());
    }

    public function testThereIsNoScheduleByDefault(): void
    {
        static::assertFalse((new TestKernel())->definitions()->has(UrssafScheduleProvider::class));
    }
}
