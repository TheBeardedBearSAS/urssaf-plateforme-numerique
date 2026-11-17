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

/**
 * The violation codes that the sources can produce, read from the sources: literal codes ('subject.reason'), the codes
 * composed by the factories of the domain exceptions from a literal subject ("<subject>.empty", "<record>.not_found",
 * "<aggregate>.invalid_state_transition") and the codes composed from a subject and a literal reason
 * ('subject' . '.reason'), once the string constants of the file are replaced by their value.
 */
final class ViolationCodes
{
    public const CODE = '[a-z][a-z0-9_]*';

    /** Factories that compose a code from their first argument, with the reason they append. */
    public const FACTORIES = [
        'InvalidValue::empty' => '.empty',
        'InvalidValue::invalidFormat' => '.invalid_format',
        'InvalidValue::negative' => '.negative',
        'InvalidValue::notPositive' => '.not_positive',
        'InvalidValue::tooLarge' => '.too_large',
        'InvalidValue::tooLong' => '.too_long',
        'InvalidValue::tooManyDecimals' => '.too_many_decimals',
        'RecordNotFound::withIdentifier' => '.not_found',
        'InvalidStateTransition::between' => '.invalid_state_transition',
    ];

    private const SUBJECT = self::CODE . '(?:\.' . self::CODE . ')*';

    private function __construct() {}

    /**
     * The codes of every scanned source, sorted.
     *
     * @return list<string>
     */
    public static function inSources(): array
    {
        $codes = [];
        foreach (ScannedSources::all() as $content) {
            array_push($codes, ...self::inSource($content));
        }
        $codes = array_values(array_unique($codes));
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * The scanned files that give a variable subject to an InvalidValue factory (their codes cannot be read).
     *
     * @return list<string>
     */
    public static function dynamicSubjectFiles(): array
    {
        $dynamic = array_filter(ScannedSources::all(), static fn(string $content): bool => preg_match('/InvalidValue::(?!because\b)\w+\(\s*\$/', $content) === 1);

        return array_keys($dynamic);
    }

    /**
     * @return list<string>
     */
    private static function inSource(string $content): array
    {
        $resolved = self::withConstantsResolved($content);
        $matches = [];
        preg_match_all('/\'(' . self::CODE . '(?:\.' . self::CODE . ')+)\'/', $resolved, $matches);
        $codes = $matches[1];
        foreach (self::FACTORIES as $call => $reason) {
            preg_match_all('/' . preg_quote($call, '/') . '\(\s*\'(' . self::SUBJECT . ')\'/', $resolved, $matches);
            array_push($codes, ...array_map(static fn(string $subject): string => $subject . $reason, $matches[1]));
        }
        preg_match_all('/\'(' . self::SUBJECT . ')\' \. \'(\.' . self::CODE . ')\'/', $resolved, $matches);
        array_push($codes, ...array_map(static fn(string $subject, string $reason): string => $subject . $reason, $matches[1], $matches[2]));

        return array_values($codes);
    }

    /**
     * The source with every "self::NAME" of a string constant `const NAME = 'value';` replaced by 'value'.
     */
    private static function withConstantsResolved(string $content): string
    {
        $matches = [];
        preg_match_all('/const ([A-Z_]+) = (\'' . self::SUBJECT . '\');/', $content, $matches);
        $names = array_map(static fn(string $name): string => 'self::' . $name, $matches[1]);

        return strtr($content, array_combine($names, $matches[2]));
    }
}
