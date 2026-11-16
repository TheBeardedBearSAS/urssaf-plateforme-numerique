<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support;

use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The PHP sources scanned for violation codes ({@see ViolationCodes}).
 */
final class ScannedSources
{
    /** Directories scanned for codes, relative to the package root. */
    private const SCANNED = [
        'src/Domain',
        'src/Application',
        'src/Infrastructure/Credentials',
        'src/Infrastructure/File',
        'src/Infrastructure/Persistence',
        'src/Infrastructure/Platform',
    ];

    /**
     * The Doctrine repository, scanned on its own: it holds the "submission.concurrent_modification" code (ADR 0011);
     * its duplicate codes are the in-memory ones.
     */
    private const SCANNED_FILES = [
        'src/Bridge/Doctrine/Repository/DoctrineSubmissionRepository.php',
    ];

    private function __construct() {}

    public static function root(): string
    {
        return \dirname(__DIR__, 4);
    }

    /**
     * The PHP sources of the scanned directories and files, by path relative to the package root.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $sources = [];
        foreach (self::SCANNED as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::root() . '/' . $directory, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                Assert::assertInstanceOf(SplFileInfo::class, $file);
                if ($file->getExtension() === 'php') {
                    $sources[substr($file->getPathname(), \strlen(self::root()) + 1)] = (string) file_get_contents($file->getPathname());
                }
            }
        }
        foreach (self::SCANNED_FILES as $file) {
            $sources[$file] = (string) file_get_contents(self::root() . '/' . $file);
        }
        ksort($sources);

        return $sources;
    }
}
