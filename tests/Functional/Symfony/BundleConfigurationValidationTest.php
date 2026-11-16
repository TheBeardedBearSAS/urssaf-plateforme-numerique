<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;

final class BundleConfigurationValidationTest extends TestCase
{
    public function testTheDoctrineDriverNeedsDoctrineBundle(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('doctrine.orm.default_entity_manager');

        Harness::boot(['persistence' => ['driver' => 'doctrine']]);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsAnInvalidConfiguration(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        Harness::boot($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        $duration = 'Invalid ISO 8601 duration';
        yield 'unknown environment' => [['environment' => 'staging'], 'Unknown environment "staging": use "production", "sandbox" or "test_api".'];
        yield 'unknown accept language' => [['accept_language' => 'DE'], 'accept_language'];
        yield 'unknown API time zone' => [['api_timezone' => 'Mars/Phobos'], 'Unknown time zone "Mars\\/Phobos".'];
        yield 'negative safety margin' => [['token_safety_margin' => -1], 'is too small for path "urssaf_plateforme_numerique.token_safety_margin"'];
        yield 'too short HTTP timeout' => [['http_timeout' => 0.05], 'http_timeout'];
        yield 'empty client id' => [['credentials' => ['tenant-1' => ['client_id' => '']]], 'client_id'];
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        yield 'missing platform id' => [['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => 'secret-2']]], 'platform_id'];
        yield 'empty tenant' => [['tenants' => ['']], 'tenants'];
        yield 'empty cache pool' => [['token_store' => ['cache_pool' => '']], 'cache_pool'];
        yield 'rate limiter without calls' => [['rate_limiter' => ['max_calls' => 0]], 'is too small for path "urssaf_plateforme_numerique.rate_limiter.max_calls"'];
        yield 'rate limiter without window' => [['rate_limiter' => ['window_seconds' => 0]], 'is too small for path "urssaf_plateforme_numerique.rate_limiter.window_seconds"'];
        yield 'lock mode' => [['lock' => ['enabled' => 'sometimes']], 'lock.enabled'];
        yield 'lock ttl below 1' => [['lock' => ['ttl' => 0.5]], 'lock.ttl'];
        yield 'empty lock factory' => [['lock' => ['factory' => '']], 'lock.factory'];
        yield 'invalid retention' => [['outbox' => ['retention' => 'forever']], $duration . ' "forever".'];
        yield 'invalid sync frequency' => [['scheduler' => ['sync_frequency' => 'often']], $duration . ' "often".'];
        yield 'invalid reconcile frequency' => [['scheduler' => ['reconcile_frequency' => 'often']], $duration];
        yield 'unknown driver' => [['persistence' => ['driver' => 'redis']], 'persistence.driver'];
        yield 'empty entity manager' => [['persistence' => ['entity_manager' => '']], 'entity_manager'];
        yield 'empty messenger bus' => [['messenger' => ['bus' => '']], 'messenger.bus'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('inconsistentConfigurations')]
    public function testRejectsAnInconsistentConfiguration(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        Harness::boot($config);
    }

    /**
     * The rules that read several options.
     *
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function inconsistentConfigurations(): iterable
    {
        yield 'credentials and provider' => [['credentials_provider' => 'my.provider', 'platform_resolver' => 'my.resolver'], '"credentials" and "credentials_provider" / "platform_resolver" are mutually exclusive.'];
        yield 'provider without resolver' => [['credentials' => null, 'credentials_provider' => 'my.provider'], '"credentials_provider" and "platform_resolver" must be configured together.'];
        yield 'nothing' => [['credentials' => null], 'Configure either "credentials" or "credentials_provider" and "platform_resolver".'];
        yield 'simulated gateway in production' => [['environment' => 'production', 'gateway' => ['simulated' => true]], '"gateway.simulated" is meant for tests and cannot be used with "environment: production".'];
        yield 'custom driver without repository' => [['persistence' => ['driver' => 'custom']], 'The "custom" driver requires "repository".'];
        yield 'custom driver outbox without storage' => [['outbox' => ['enabled' => true], 'persistence' => ['driver' => 'custom', 'repository' => 'test.submissions']], 'The "custom" persistence driver requires "persistence.outbox" when the outbox is enabled.'];
        yield 'custom sink without service' => [['outbox' => ['enabled' => true, 'sink' => 'custom']], 'The "custom" outbox sink requires "outbox.service".'];
        yield 'unknown sink' => [['outbox' => ['sink' => 'kafka']], 'outbox.sink'];
    }

    /**
     * @param array<string, mixed> $workflow
     */
    #[DataProvider('invalidWorkflows')]
    public function testRejectsAnInvalidWorkflow(array $workflow, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        Harness::boot(['workflow' => $workflow]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidWorkflows(): iterable
    {
        $duration = 'Invalid ISO 8601 duration';
        yield 'default page size above max' => [['default_page_size' => 200, 'max_page_size' => 100], '"default_page_size" must not be greater than "max_page_size".'];
        yield 'invalid status poll interval' => [['status_poll_interval' => 'soon'], $duration];
        yield 'invalid payment poll interval' => [['payment_poll_interval' => 'soon'], $duration];
        yield 'invalid polling age' => [['max_polling_age' => 'never'], $duration];
        yield 'invalid manual review delay' => [['manual_review_after' => 'later'], $duration];
        yield 'zero stale upload delay' => [['stale_upload_after' => 'PT0S'], 'Invalid ISO 8601 duration "PT0S": it must be greater than zero.'];
        yield 'no upload attempt' => [['max_upload_attempts' => 0], 'is too small for path "urssaf_plateforme_numerique.workflow.max_upload_attempts"'];
        yield 'no reconciliation page' => [['reconciliation_max_pages' => 0], 'is too small for path "urssaf_plateforme_numerique.workflow.reconciliation_max_pages"'];
        yield 'null default page size' => [['default_page_size' => 0], 'is too small for path "urssaf_plateforme_numerique.workflow.default_page_size"'];
        yield 'null max page size' => [['default_page_size' => 1, 'max_page_size' => 0], 'is too small for path "urssaf_plateforme_numerique.workflow.max_page_size"'];
    }

    public function testTheEnvironmentIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('environment');

        (new TestKernel(['environment' => null]))->boot();
    }
}
