<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\Violation;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;

final class ViolationListConverterWiringTest extends TestCase
{
    public function testTheConverterIsRegisteredWithTheOptionalTranslator(): void
    {
        // Compiled afresh: the booted test containers are cached by configuration, not by source code.
        $definition = (new TestKernel())->definitions()->getDefinition(ViolationListConverter::class);

        static::assertTrue($definition->isPublic());
        static::assertEquals([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $definition->getArguments());
    }

    public function testTheConverterIsPublicAndTranslatesWithTheShippedMessages(): void
    {
        $app = Harness::boot();
        $app->privateService('translator', LocaleAwareInterface::class)->setLocale('fr');
        $result = ValidationResult::of(Violation::error('search', 'submission_search.search_empty', 'The search keyword must not be empty when given.'));

        $violation = $app->get(ViolationListConverter::class)->convert($result, 'filters')->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Le mot-clé de recherche ne doit pas être vide.', $violation->getMessage());
        static::assertSame('filters.search', $violation->getPropertyPath());
    }
}
