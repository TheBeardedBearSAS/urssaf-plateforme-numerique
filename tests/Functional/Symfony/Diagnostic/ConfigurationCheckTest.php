<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Diagnostic;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic\ConfigurationCheck;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;

final class ConfigurationCheckTest extends TestCase
{
    public function testAValidConfigurationIsReported(): void
    {
        $check = new ConfigurationCheck(static fn(): ApiConfiguration => ApiConfiguration::forEnvironment(Environment::Sandbox));

        $results = $check->run(self::context());

        static::assertCount(1, $results);
        static::assertSame(['configuration', '', CheckStatus::Ok], [$results[0]->check, $results[0]->subject, $results[0]->status]);
        static::assertMatchesRegularExpression('/^Environment "sandbox", base URI "[^"]+", API time zone "Europe\/Paris"\.$/', $results[0]->detail);
    }

    public function testAnUnknownEnvironmentIsAFailure(): void
    {
        $check = new ConfigurationCheck(static fn(): ApiConfiguration => ApiConfiguration::forEnvironment(Environment::from('staging')));

        $results = $check->run(self::context());

        static::assertCount(1, $results);
        static::assertSame(['configuration', '', CheckStatus::Failure], [$results[0]->check, $results[0]->subject, $results[0]->status]);
        static::assertStringContainsString('staging', $results[0]->detail);
    }

    public function testAnInvalidValueResolvedAtRuntimeIsAFailure(): void
    {
        $_ENV['URSSAF_TEST_HEADER'] = 'bad header';
        try {
            $check = Harness::boot(['request_id_header' => '%env(URSSAF_TEST_HEADER)%'])->privateService(ConfigurationCheck::class, ConfigurationCheck::class);

            static::assertEquals(
                [new CheckResult('configuration', '', CheckStatus::Failure, 'Invalid request_id_header: the value does not match the expected format (an HTTP header name).')],
                $check->run(self::context()),
            );
        } finally {
            unset($_ENV['URSSAF_TEST_HEADER']);
        }
    }

    private static function context(): CheckContext
    {
        return CheckContext::withRemoteCalls([TenantId::fromString('tenant-1')]);
    }
}
