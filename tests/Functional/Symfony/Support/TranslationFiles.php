<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support;

use PHPUnit\Framework\Assert;
use Symfony\Component\Yaml\Yaml;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Validation\ViolationListConverter;

/**
 * The translation files shipped by the bundle.
 */
final class TranslationFiles
{
    public const LOCALES = ['en', 'fr'];

    private function __construct() {}

    /**
     * @return array<string, string> the messages of the locale, by violation code, in file order
     */
    public static function messages(string $locale): array
    {
        // @mago-expect analysis:mixed-assignment YAML is untyped; every entry is checked below.
        $parsed = Yaml::parseFile(ScannedSources::root() . '/src/Bridge/Symfony/Resources/translations/' . ViolationListConverter::TRANSLATION_DOMAIN . '.' . $locale . '.yaml');
        Assert::assertIsArray($parsed);
        $messages = [];
        foreach ($parsed as $code => $message) { // @mago-expect analysis:mixed-assignment narrowed by the assertion below.
            Assert::assertIsString($message);
            $messages[(string) $code] = $message;
        }

        return $messages;
    }

    /**
     * @return list<string> the "%name%" placeholders of a message, sorted
     */
    public static function placeholders(string $message): array
    {
        $matches = [];
        preg_match_all('/%[a-z_]+%/', $message, $matches);
        $placeholders = $matches[0];
        sort($placeholders);

        return $placeholders;
    }
}
