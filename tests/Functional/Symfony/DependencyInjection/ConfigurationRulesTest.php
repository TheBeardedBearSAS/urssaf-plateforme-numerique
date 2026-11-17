<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\DependencyInjection\ConfigurationRules;

final class ConfigurationRulesTest extends TestCase
{
    public function testThePageSizes(): void
    {
        static::assertTrue(ConfigurationRules::defaultPageAboveMax(['default_page_size' => 2, 'max_page_size' => 1]));
        static::assertFalse(ConfigurationRules::defaultPageAboveMax(['default_page_size' => 1, 'max_page_size' => 1]));
    }

    public function testTheCustomDriverNeedsARepository(): void
    {
        static::assertTrue(ConfigurationRules::customDriverWithoutRepository(['driver' => 'custom', 'repository' => null]));
        static::assertFalse(ConfigurationRules::customDriverWithoutRepository(['driver' => 'custom', 'repository' => 'my.submissions']));
        static::assertFalse(ConfigurationRules::customDriverWithoutRepository(['driver' => 'in_memory', 'repository' => null]));
    }

    #[DataProvider('outboxes')]
    public function testTheOutboxOfTheCustomDriverNeedsItsStorage(bool $enabled, ?string $service, string $driver, bool $expected): void
    {
        static::assertSame($expected, ConfigurationRules::outboxWithoutStorage([
            'outbox' => ['enabled' => $enabled],
            'persistence' => ['driver' => $driver, 'outbox' => $service],
        ]));
    }

    /**
     * @return iterable<string, array{bool, ?string, string, bool}>
     */
    public static function outboxes(): iterable
    {
        yield 'custom without service' => [true, null, 'custom', true];
        yield 'custom with service' => [true, 'my.outbox', 'custom', false];
        yield 'disabled' => [false, null, 'custom', false];
        yield 'in memory' => [true, null, 'in_memory', false];
    }

    #[DataProvider('sinks')]
    public function testTheCustomSinkNeedsItsService(bool $enabled, string $sink, ?string $service, bool $expected): void
    {
        static::assertSame($expected, ConfigurationRules::customSinkWithoutService(['enabled' => $enabled, 'sink' => $sink, 'service' => $service]));
    }

    /**
     * @return iterable<string, array{bool, string, ?string, bool}>
     */
    public static function sinks(): iterable
    {
        yield 'custom without service' => [true, 'custom', null, true];
        yield 'custom with service' => [true, 'custom', 'my.sink', false];
        yield 'disabled' => [false, 'custom', null, false];
        yield 'messenger' => [true, 'messenger', null, false];
    }

    public function testTheSimulatedGatewayIsRefusedInProduction(): void
    {
        static::assertTrue(ConfigurationRules::simulatedGatewayInProduction(['environment' => 'production', 'gateway' => ['simulated' => true]]));
        static::assertFalse(ConfigurationRules::simulatedGatewayInProduction(['environment' => 'sandbox', 'gateway' => ['simulated' => true]]));
        static::assertFalse(ConfigurationRules::simulatedGatewayInProduction(['environment' => 'production', 'gateway' => ['simulated' => false]]));
    }

    /**
     * @param array<string, mixed>                            $config
     * @param array{both: bool, none: bool, incomplete: bool} $expected
     */
    #[DataProvider('credentialSources')]
    public function testTheCredentialSources(array $config, array $expected): void
    {
        static::assertSame($expected['both'], ConfigurationRules::hasBothCredentialSources($config));
        static::assertSame($expected['none'], ConfigurationRules::hasNoCredentialSource($config));
        static::assertSame($expected['incomplete'], ConfigurationRules::hasIncompleteCredentialServices($config));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array{both: bool, none: bool, incomplete: bool}}>
     */
    public static function credentialSources(): iterable
    {
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        $credentials = ['tenant-1' => ['client_id' => 'a', 'client_secret' => 'b', 'platform_id' => 'c']];
        $sources = static fn(array $credentials, ?string $provider, ?string $resolver): array => ['credentials' => $credentials, 'credentials_provider' => $provider, 'platform_resolver' => $resolver];

        yield 'credentials only' => [$sources($credentials, null, null), ['both' => false, 'none' => false, 'incomplete' => false]];
        yield 'services only' => [$sources([], 'p', 'r'), ['both' => false, 'none' => false, 'incomplete' => false]];
        yield 'nothing' => [$sources([], null, null), ['both' => false, 'none' => true, 'incomplete' => false]];
        yield 'credentials and provider' => [$sources($credentials, 'p', null), ['both' => true, 'none' => false, 'incomplete' => true]];
        yield 'credentials and resolver' => [$sources($credentials, null, 'r'), ['both' => true, 'none' => false, 'incomplete' => true]];
        yield 'credentials and both services' => [$sources($credentials, 'p', 'r'), ['both' => true, 'none' => false, 'incomplete' => false]];
        yield 'provider without resolver' => [$sources([], 'p', null), ['both' => false, 'none' => false, 'incomplete' => true]];
        yield 'resolver without provider' => [$sources([], null, 'r'), ['both' => false, 'none' => false, 'incomplete' => true]];
    }
}
