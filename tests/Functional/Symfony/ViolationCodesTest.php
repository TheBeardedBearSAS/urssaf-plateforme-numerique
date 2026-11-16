<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\ScannedSources;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\ViolationCodes;

/**
 * The scan of the violation codes finds what the factories of the domain exceptions compose.
 */
final class ViolationCodesTest extends TestCase
{
    /**
     * Files that give a variable subject to an InvalidValue factory: all of them must produce technical codes only
     * (TranslationsTest). A new one fails the test until its codes are handled.
     */
    private const DYNAMIC_SUBJECT_FILES = [
        'src/Application/Policy/WorkflowPolicy.php',
    ];

    public function testTheScanFindsTheComposedCodes(): void
    {
        $codes = ViolationCodes::inSources();

        foreach (['tenant_id.too_long', 'platform.not_found', 'submission.invalid_state_transition', 'file_name.invalid_character', 'submission.concurrent_modification', 'workflow_policy.stale_upload_after.not_positive', 'submission_search.search_empty'] as $code) {
            static::assertContains($code, $codes);
        }
    }

    public function testTheFactoriesAreTheOnesOfInvalidValue(): void
    {
        $content = (string) file_get_contents(ScannedSources::root() . '/src/Domain/Exception/InvalidValue.php');
        $factories = [];
        $matches = [];
        preg_match_all('/public static function (\w+)\(string \$subject[^{]*\{[^}]*\$subject \. \'(\.[a-z_]+)\'/', $content, $matches);
        foreach ($matches[1] as $index => $name) {
            $factories['InvalidValue::' . $name] = $matches[2][$index];
        }
        $expected = array_filter(ViolationCodes::FACTORIES, static fn(string $call): bool => str_starts_with($call, 'InvalidValue::'), ARRAY_FILTER_USE_KEY);
        ksort($factories);

        static::assertSame($expected, $factories);
    }

    public function testTheDynamicSubjectFilesAreKnown(): void
    {
        static::assertSame(self::DYNAMIC_SUBJECT_FILES, ViolationCodes::dynamicSubjectFiles());
    }
}
