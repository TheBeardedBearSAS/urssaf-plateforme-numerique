<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Files written by the command tests, under the git-ignored "var" directory, removed when the process ends.
 */
final class ScratchFiles
{
    private static ?string $directory = null;

    private function __construct() {}

    /**
     * A new directory of the process.
     */
    public static function directory(): string
    {
        if (self::$directory === null) {
            self::$directory = \dirname(__DIR__, 4) . '/var/t8/files/' . bin2hex(random_bytes(8));
            (new Filesystem())->mkdir(self::$directory);
            register_shutdown_function(static function (): void {
                try {
                    (new Filesystem())->remove((string) self::$directory);
                } catch (IOException) {
                    // @mago-expect lint:no-empty-catch-clause Scratch files are disposable: a directory left behind is harmless.
                }
            });
        }

        return self::$directory;
    }

    /**
     * The path of a file with the given name and content, in a directory of its own.
     */
    public static function file(string $name, string $content): string
    {
        $directory = self::directory() . '/' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($directory);
        $path = $directory . '/' . $name;
        (new Filesystem())->dumpFile($path, $content);

        return $path;
    }
}
