<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;

final class EnvironmentTest extends TestCase
{
    /**
     * @return iterable<string, array{Environment, array{value: string, baseUri: string, basePath: string, scope: string, newsFeed: bool}}>
     */
    public static function targets(): iterable
    {
        yield 'production' => [
            Environment::Production,
            [
                'value' => 'production',
                'baseUri' => 'https://api.urssaf.fr',
                'basePath' => '/api-odspn-be-platforms/v1',
                'scope' => 'ods.pn.platforms',
                'newsFeed' => true,
            ],
        ];
        yield 'sandbox' => [
            Environment::Sandbox,
            [
                'value' => 'sandbox',
                'baseUri' => 'https://api-edi.urssaf.fr',
                'basePath' => '/api-odspn-be-platforms/v1',
                'scope' => 'ods.pn.platforms',
                'newsFeed' => true,
            ],
        ];
        yield 'test api' => [
            Environment::TestApi,
            [
                'value' => 'test_api',
                'baseUri' => 'https://api.urssaf.fr',
                'basePath' => '/api-odspn-be-platforms-sandbox/v1',
                'scope' => 'ods.pn.platforms.sandbox',
                'newsFeed' => false,
            ],
        ];
    }

    /**
     * @param array{value: string, baseUri: string, basePath: string, scope: string, newsFeed: bool} $expected
     */
    #[DataProvider('targets')]
    public function testEachTargetHasItsHostBasePathAndScope(Environment $environment, array $expected): void
    {
        static::assertSame($expected['value'], $environment->value);
        static::assertSame($expected['baseUri'], $environment->baseUri());
        static::assertSame($expected['basePath'], $environment->basePath());
        static::assertSame($expected['scope'], $environment->scope());
        static::assertSame($expected['newsFeed'], $environment->supportsNewsFeed());
    }

    public function testThereAreExactlyThreeTargets(): void
    {
        static::assertCount(3, Environment::cases());
    }
}
