<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\TranslationFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\ViolationCodes;

/**
 * Every violation code that the sources can produce has a message in every shipped locale, and nothing else does.
 */
final class TranslationsTest extends TestCase
{
    /** Codes that are technical (configuration, wire format, programming errors) and never shown to an end user. */
    private const NOT_TRANSLATED = [
        'access_token.empty',
        'access_token.negative_margin',
        'credentials.client_id_empty',
        'credentials.client_secret_empty',
        'polling_window.negative',
        'validation.failed',
        'violation.invalid_code',
        'violation.invalid_path',
    ];

    /**
     * The settings of the WorkflowPolicy: configuration errors, caught first by the build-time rules of the bundle. The
     * scan also reads their subjects ("workflow_policy.max_upload_attempts"), which are not codes.
     */
    private const NOT_TRANSLATED_PREFIX = 'workflow_policy.';

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        foreach (TranslationFiles::LOCALES as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('locales')]
    public function testEveryViolationCodeHasAMessage(string $locale): void
    {
        $missing = array_values(array_diff(self::translatableCodes(), array_keys(TranslationFiles::messages($locale))));

        static::assertSame([], $missing, 'Violation codes without a message in "' . $locale . '".');
    }

    #[DataProvider('locales')]
    public function testNoMessageWithoutCode(string $locale): void
    {
        $orphans = array_values(array_diff(array_keys(TranslationFiles::messages($locale)), self::translatableCodes()));

        static::assertSame([], $orphans, 'Messages of "' . $locale . '" for a code that no source produces.');
    }

    public function testTheTechnicalCodesAreProducedByTheSources(): void
    {
        static::assertSame([], array_values(array_diff(self::NOT_TRANSLATED, ViolationCodes::inSources())));
    }

    #[DataProvider('locales')]
    public function testEveryMessageIsAFilledSentence(string $locale): void
    {
        foreach (TranslationFiles::messages($locale) as $code => $message) {
            static::assertMatchesRegularExpression('/^' . ViolationCodes::CODE . '(\.' . ViolationCodes::CODE . ')+$/', $code);
            static::assertMatchesRegularExpression('/^\p{Lu}.+\.$/u', $message, $code);
        }
    }

    #[DataProvider('locales')]
    public function testEveryLocaleHasTheKeysOfTheEnglishFileInTheSameOrder(string $locale): void
    {
        static::assertSame(array_keys(TranslationFiles::messages('en')), array_keys(TranslationFiles::messages($locale)));
    }

    #[DataProvider('locales')]
    public function testTheKeysAreSorted(string $locale): void
    {
        $keys = array_keys(TranslationFiles::messages($locale));
        $sorted = $keys;
        sort($sorted, SORT_STRING);

        static::assertSame($sorted, $keys);
    }

    #[DataProvider('locales')]
    public function testEveryLocaleHasThePlaceholdersOfTheEnglishMessage(string $locale): void
    {
        $english = TranslationFiles::messages('en');
        foreach (TranslationFiles::messages($locale) as $code => $message) {
            static::assertSame(TranslationFiles::placeholders($english[$code]), TranslationFiles::placeholders($message), $code);
        }
    }

    public function testTheFrameworkTranslatorLoadsTheDomain(): void
    {
        $translator = Harness::boot()->privateService('translator', TranslatorInterface::class);

        static::assertSame(
            'Aucune plateforme URSSAF n\'est configurée pour ce locataire.',
            $translator->trans('platform.not_found', [], ViolationListConverter::TRANSLATION_DOMAIN, 'fr'),
        );
        static::assertSame(
            'The tenant identifier must not exceed 64 characters.',
            $translator->trans('tenant_id.too_long', ['%max_length%' => '64'], ViolationListConverter::TRANSLATION_DOMAIN, 'en'),
        );
    }

    /**
     * The codes of the sources, without the technical ones.
     *
     * @return list<string>
     */
    private static function translatableCodes(): array
    {
        return array_values(array_filter(
            array_diff(ViolationCodes::inSources(), self::NOT_TRANSLATED),
            static fn(string $code): bool => !str_starts_with($code, self::NOT_TRANSLATED_PREFIX),
        ));
    }
}
