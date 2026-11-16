<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

final class StringIdentifiersTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<TenantId|ExternalReference|PlatformId|SubmissionId|UrssafReference|FileName>, string, positive-int}>
     */
    public static function provideFactories(): iterable
    {
        yield 'tenant id' => [TenantId::class, 'tenant_id', 64];
        yield 'external reference' => [ExternalReference::class, 'external_reference', 64];
        yield 'platform id' => [PlatformId::class, 'platform_id', 64];
        yield 'submission id' => [SubmissionId::class, 'submission_id', 64];
        yield 'urssaf reference' => [UrssafReference::class, 'urssaf_reference', 255];
        yield 'file name' => [FileName::class, 'file_name', 255];
    }

    /**
     * @param class-string<TenantId|ExternalReference|PlatformId|SubmissionId|UrssafReference|FileName> $class
     * @param positive-int                                                                              $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testValuesAreKeptVerbatim(string $class, string $subject, int $maxLength): void
    {
        foreach (['a', ' padded value ', str_repeat('x', $maxLength), str_repeat('é', $maxLength)] as $value) {
            $identifier = $class::fromString($value);
            static::assertSame($value, $identifier->value());
            static::assertSame($value, (string) $identifier);
        }
        static::assertSame($class::MAX_LENGTH, $maxLength);
    }

    /**
     * @param class-string<TenantId|ExternalReference|PlatformId|SubmissionId|UrssafReference|FileName> $class
     * @param positive-int                                                                              $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testEmptyValuesAreRejected(string $class, string $subject, int $maxLength): void
    {
        try {
            $class::fromString('');
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($subject . '.empty', $exception->violationCode());
        }
    }

    /**
     * @param class-string<TenantId|ExternalReference|PlatformId|SubmissionId|UrssafReference|FileName> $class
     * @param positive-int                                                                              $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testTooLongValuesAreRejected(string $class, string $subject, int $maxLength): void
    {
        foreach ([str_repeat('x', $maxLength + 1), str_repeat('é', $maxLength + 1)] as $value) {
            try {
                $class::fromString($value);
                static::fail('Expected exception');
            } catch (InvalidValue $exception) {
                static::assertSame($subject . '.too_long', $exception->violationCode());
                static::assertSame(['max_length' => $maxLength], $exception->params());
            }
        }
    }

    /**
     * @return iterable<string, array{class-string<PlatformId|UrssafReference>, string}>
     */
    public static function providePathIdentifiers(): iterable
    {
        yield 'platform id' => [PlatformId::class, 'platform_id'];
        yield 'urssaf reference' => [UrssafReference::class, 'urssaf_reference'];
    }

    /**
     * @param class-string<PlatformId|UrssafReference> $class
     */
    #[DataProvider('providePathIdentifiers')]
    public function testSlashIsRejectedWhereTheValueGoesInAPath(string $class, string $subject): void
    {
        try {
            $class::fromString('a/b');
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($subject . '.invalid_format', $exception->violationCode());
            static::assertSame(['expected_format' => 'no "/" character'], $exception->params());
        }
    }

    public function testSlashIsAcceptedElsewhere(): void
    {
        static::assertSame('a/b', TenantId::fromString('a/b')->value());
        static::assertSame('a/b', ExternalReference::fromString('a/b')->value());
        static::assertSame('a/b', SubmissionId::fromString('a/b')->value());
        static::assertSame('a/b', FileName::fromString('a/b')->value());
    }

    public function testTenantIdRejectsBlankValues(): void
    {
        foreach ([' ', "\t\n"] as $value) {
            try {
                TenantId::fromString($value);
                static::fail('Expected exception');
            } catch (InvalidValue $exception) {
                static::assertSame('tenant_id.empty', $exception->violationCode());
            }
        }
    }

    public function testBlankValuesAreKeptWhereTheReferenceIsVerbatim(): void
    {
        static::assertSame(' ', ExternalReference::fromString(' ')->value());
        static::assertSame(' ', SubmissionId::fromString(' ')->value());
    }

    public function testEquality(): void
    {
        static::assertTrue(TenantId::fromString('acme')->equals(TenantId::fromString('acme')));
        static::assertFalse(TenantId::fromString('acme')->equals(TenantId::fromString('Acme')));
        static::assertTrue(ExternalReference::fromString('r1')->equals(ExternalReference::fromString('r1')));
        static::assertFalse(ExternalReference::fromString('r1')->equals(ExternalReference::fromString('r2')));
        static::assertTrue(PlatformId::fromString('p1')->equals(PlatformId::fromString('p1')));
        static::assertFalse(PlatformId::fromString('p1')->equals(PlatformId::fromString('p2')));
        static::assertTrue(SubmissionId::fromString('s1')->equals(SubmissionId::fromString('s1')));
        static::assertFalse(SubmissionId::fromString('s1')->equals(SubmissionId::fromString('s2')));
        static::assertTrue(UrssafReference::fromString('u1')->equals(UrssafReference::fromString('u1')));
        static::assertFalse(UrssafReference::fromString('u1')->equals(UrssafReference::fromString('u2')));
        static::assertTrue(FileName::fromString('f.csv')->equals(FileName::fromString('f.csv')));
        static::assertFalse(FileName::fromString('f.csv')->equals(FileName::fromString('g.csv')));
    }
}
