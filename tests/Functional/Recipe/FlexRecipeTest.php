<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Recipe;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\UrssafPlateformeNumeriqueBundle;
use TheBeardedBear\UrssafPlateformeNumerique\Testing\SimulatedPlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;

/**
 * The Symfony Flex recipe stays in line with the bundle, and the endpoint generator publishes it.
 */
final class FlexRecipeTest extends TestCase
{
    private const RECIPE = __DIR__ . '/../../../recipe/thebeardedbear/urssaf-plateforme-numerique/0.1';

    public function testTheManifestRegistersTheBundleAndCopiesTheConfiguration(): void
    {
        // @mago-expect analysis:mixed-assignment JSON is untyped: the assertions below narrow it.
        $manifest = json_decode((string) file_get_contents(self::RECIPE . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

        static::assertIsArray($manifest);
        static::assertSame([UrssafPlateformeNumeriqueBundle::class => ['all']], $manifest['bundles']);
        static::assertSame(['config/' => '%CONFIG_DIR%/'], $manifest['copy-from-recipe']);
        static::assertIsArray($manifest['env']);
        static::assertSame(['URSSAF_PN_ENV' => 'sandbox', 'URSSAF_PN_CLIENT_ID' => '', 'URSSAF_PN_CLIENT_SECRET' => '', 'URSSAF_PN_PLATFORM_ID' => ''], array_diff_key($manifest['env'], ['#1' => true]));
    }

    public function testTheCopiedConfigurationBootsTheBundle(): void
    {
        /** @var array{urssaf_plateforme_numerique: array<string, mixed>, 'when@test': array{urssaf_plateforme_numerique: array<string, mixed>}} $config */
        $config = self::recipeConfig();
        $main = $config['urssaf_plateforme_numerique'];
        // @mago-expect lint:no-literal-password Fake credentials of a throw-away test configuration.
        $variables = ['URSSAF_PN_ENV' => 'sandbox', 'URSSAF_PN_CLIENT_ID' => 'client', 'URSSAF_PN_CLIENT_SECRET' => 'secret', 'URSSAF_PN_PLATFORM_ID' => 'platform'];
        foreach ($variables as $name => $value) {
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
        }
        try {
            // Merged over the test defaults (HTTP client, in-memory persistence, a test tenant).
            $harness = Harness::boot($main);
            $command = $harness->command('urssaf:pn:check');
            static::assertSame(0, $command->execute(['--skip-remote' => true]));
            static::assertMatchesRegularExpression('/^\| credentials +\| default +\| ok /m', $command->getDisplay());

            static::assertTrue(Harness::boot([...$main, ...$config['when@test']['urssaf_plateforme_numerique']])->has(SimulatedPlatformGateway::class));
        } finally {
            foreach (array_keys($variables) as $name) {
                unset($_SERVER[$name], $_ENV[$name]);
            }
        }
    }

    public function testTheEndpointGeneratorPublishesTheRecipe(): void
    {
        $output = \dirname(__DIR__, 3) . '/var/test-flex-endpoint';
        (new Filesystem())->remove($output);
        $process = new Process([PHP_BINARY, \dirname(__DIR__, 3) . '/bin/build-flex-endpoint', $output, 'https://flex.example.com/urssaf/']);

        static::assertSame(0, $process->run());

        $index = (string) file_get_contents($output . '/index.json');
        static::assertStringContainsString('"aliases": {}', $index);
        static::assertSame([
            'aliases' => [],
            'recipes' => ['thebeardedbear/urssaf-plateforme-numerique' => ['0.1']],
            'branch' => 'main',
            'is_contrib' => false,
            '_links' => [
                'repository' => 'https://flex.example.com/urssaf',
                'origin_template' => '{package}:{version}@https://flex.example.com/urssaf',
                'recipe_template' => 'https://flex.example.com/urssaf/{package_dotted}.{version}.json',
            ],
        ], json_decode($index, true, 512, JSON_THROW_ON_ERROR));
        /** @var array{manifests: array<string, array{manifest: mixed, files: array<string, array{contents: list<string>, executable: bool}>, ref: string}>} $recipe */
        $recipe = json_decode((string) file_get_contents($output . '/thebeardedbear.urssaf-plateforme-numerique.0.1.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest = $recipe['manifests']['thebeardedbear/urssaf-plateforme-numerique'];
        static::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $manifest['ref']);
        static::assertSame(['config/packages/urssaf_plateforme_numerique.yaml'], array_keys($manifest['files']));
        $file = $manifest['files']['config/packages/urssaf_plateforme_numerique.yaml'];
        static::assertFalse($file['executable']);
        static::assertSame((string) file_get_contents(self::RECIPE . '/config/packages/urssaf_plateforme_numerique.yaml'), implode("\n", $file['contents']) . "\n");
        static::assertSame(json_decode((string) file_get_contents(self::RECIPE . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR), $manifest['manifest']);
        (new Filesystem())->remove($output);
    }

    public function testTheGeneratorNeedsItsArguments(): void
    {
        $process = new Process([PHP_BINARY, \dirname(__DIR__, 3) . '/bin/build-flex-endpoint']);

        static::assertSame(2, $process->run());
        static::assertStringContainsString('Usage: bin/build-flex-endpoint <output-dir> <base-url>', $process->getErrorOutput());
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function recipeConfig(): array
    {
        // @mago-expect analysis:mixed-assignment YAML is untyped: the assertion below narrows it.
        $config = Yaml::parseFile(self::RECIPE . '/config/packages/urssaf_plateforme_numerique.yaml');
        static::assertIsArray($config);

        return $config;
    }
}
