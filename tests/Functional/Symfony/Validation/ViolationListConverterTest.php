<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\Violation;

final class ViolationListConverterTest extends TestCase
{
    public function testEveryErrorBecomesAConstraintViolationWithTheCodeAndThePath(): void
    {
        $error = Violation::error('files[0].name', 'file_name.too_long', 'The file name is too long.');

        $list = (new ViolationListConverter(null))->convert(ValidationResult::of($error));

        static::assertCount(1, $list);
        $violation = self::first($list);
        static::assertSame('The file name is too long.', $violation->getMessage());
        static::assertSame('file_name.too_long', $violation->getMessageTemplate());
        static::assertSame('file_name.too_long', $violation->getCode());
        static::assertSame('files[0].name', $violation->getPropertyPath());
        static::assertSame([], $violation->getParameters());
        static::assertNull($violation->getRoot());
        static::assertNull($violation->getInvalidValue());
        static::assertNull($violation->getPlural());
        static::assertNull($violation->getConstraint());
        static::assertSame($error, $violation->getCause());
    }

    public function testTheViolationsOfAValidationFailureAreConverted(): void
    {
        $result = ValidationResult::of(
            Violation::error('search', 'submission_search.search_empty', 'The search keyword must not be empty.'),
            Violation::error('type', 'submission_search.type_invalid', 'The type must be DECLARATION or REGULARISATION.'),
        );

        $list = (new ViolationListConverter(null))->convert(ValidationFailed::withResult($result));

        static::assertSame(['search', 'type'], self::paths($list));
    }

    public function testThePathPrefixIsPrependedToEveryPath(): void
    {
        $result = ValidationResult::of(
            Violation::error('page.size', 'page_request.size_out_of_range', 'The page size is out of range.'),
            Violation::error('', 'validation.failed', 'The data is not valid.'),
        );

        $list = (new ViolationListConverter(null))->convert($result, 'search');

        static::assertSame(['search.page.size', 'search'], self::paths($list));
    }

    public function testWarningsAreKeptOnlyOnRequest(): void
    {
        $result = ValidationResult::of(
            Violation::warning('externalReference', 'external_reference.unusual', 'Unusual external reference.'),
            Violation::error('tenantId', 'tenant_id.empty', 'The tenant identifier must not be empty.'),
        );
        $converter = new ViolationListConverter(null);

        static::assertSame(['tenantId'], self::paths($converter->convert($result)));
        static::assertSame(['externalReference', 'tenantId'], self::paths($converter->convertWithWarnings($result, '')));
    }

    public function testTheParametersBecomePlaceholdersAndAreGivenToTheTranslator(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'submission_search.search_too_long' => 'Au plus %max% (%ratio%, %allowed%, %blocked%, %unit%).',
        ], 'fr', 'urssaf_plateforme_numerique');
        $translator->addResource('array', ['submission_search.search_too_long' => 'Wrong domain.'], 'fr', 'messages');
        $error = Violation::error('search', 'submission_search.search_too_long', 'The search keyword is too long.', [
            'max' => 10,
            'ratio' => 0.5,
            'allowed' => true,
            'blocked' => false,
            'unit' => 'CHAR',
        ]);

        $violation = self::first((new ViolationListConverter($translator))->convert(ValidationResult::of($error)));

        $parameters = ['%max%' => '10', '%ratio%' => '0.5', '%allowed%' => 'true', '%blocked%' => 'false', '%unit%' => 'CHAR'];
        static::assertSame('Au plus 10 (0.5, true, false, CHAR).', $violation->getMessage());
        static::assertSame($parameters, $violation->getParameters());
    }

    private static function first(ConstraintViolationList $list): ConstraintViolation
    {
        $violation = $list->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);

        return $violation;
    }

    /**
     * @return list<string>
     */
    private static function paths(ConstraintViolationList $list): array
    {
        $paths = [];
        foreach ($list as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }
}
