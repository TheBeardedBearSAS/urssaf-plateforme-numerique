<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\SubmissionErrorsType;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\Type\SubmissionSnapshotType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;

/**
 * Conversion scenarios of the JSON column types (reference JsonTypesTest): one method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class JsonTypesTest extends TestCase
{
    private const ERRORS_TYPE = 'list<' . SubmissionError::class . '>';

    private const EMPTY_FILES = '"reportFiles":{"globalReport":null,"detailedReport":null,"usersAnomaliesReport":null,"affiliatedUsersFile":null}';

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    /**
     * @return iterable<string, array{string}> case names of {@see self::document()}
     */
    public static function documents(): iterable
    {
        yield 'full snapshot' => ['snapshot-full'];
        yield 'snapshot with nulls' => ['snapshot-nulls'];
        yield 'errors with and without values' => ['errors'];
        yield 'no error' => ['no-errors'];
    }

    #[DataProvider('documents')]
    public function testItStoresAnExplicitDocumentAndRebuildsTheValue(string $case): void
    {
        [$name, $value, $json] = self::document($case);
        $type = Type::getType($name);

        static::assertSame($json, $type->convertToDatabaseValue($value, $this->platform));
        static::assertEquals($value, $type->convertToPHPValue($json, $this->platform));
    }

    public function testTheRawCodesAndPeriodsOfASnapshotSurviveTheRoundTrip(): void
    {
        $type = new SubmissionSnapshotType();
        $snapshot = new SubmissionSnapshot('LATER', 'SOON', [ReportedPeriod::fromWire('2026-02'), ReportedPeriod::fromWire('T1')], ReportFiles::none(), [], null);

        $json = $type->convertToDatabaseValue($snapshot, $this->platform);
        static::assertIsString($json);
        $read = $type->convertToPHPValue($json, $this->platform);

        static::assertInstanceOf(SubmissionSnapshot::class, $read);
        static::assertSame('LATER', $read->rawStatus());
        static::assertNull($read->status());
        static::assertSame('SOON', $read->rawPaymentStatus());
        static::assertNull($read->paymentStatus());
        static::assertSame('2026-02', $read->periods()[0]->period()?->value());
        static::assertSame('T1', $read->periods()[1]->raw());
        static::assertNull($read->periods()[1]->period());
    }

    public function testCountersKeepTheirWireNamesAndDecimalStrings(): void
    {
        $type = new SubmissionSnapshotType();
        $json = '{"rawStatus":null,"rawPaymentStatus":null,"periods":[],' . self::EMPTY_FILES
            . ',"counters":{"numberOfError":"0","totalAmount":"1234.50"},"lastUpdateDate":null}';

        $read = $type->convertToPHPValue($json, $this->platform);

        static::assertInstanceOf(SubmissionSnapshot::class, $read);
        static::assertSame(['numberOfError', 'totalAmount'], array_keys($read->counters()));
        static::assertSame('1234.50', $read->counters()['totalAmount']->toString());
    }

    #[DataProvider('documents')]
    public function testNullStaysNull(string $case): void
    {
        [$name] = self::document($case);
        $type = Type::getType($name);

        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
    }

    #[DataProvider('documents')]
    public function testItRefusesAForeignPhpValue(string $case): void
    {
        [$name, , , $expected] = self::document($case);

        try {
            Type::getType($name)->convertToDatabaseValue('invalid', $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . $expected . ', null.', $exception->getMessage());
        }
    }

    #[DataProvider('documents')]
    public function testItRefusesAStoredValueThatIsNotValidJsonObjectOrArray(string $case): void
    {
        [$name] = self::document($case);
        $messages = [];
        foreach ([12, '{not json', '12', '"text"'] as $stored) {
            try {
                Type::getType($name)->convertToPHPValue($stored, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        static::assertStringEndsWith('types: string, null.', $messages[0]);
        static::assertStringContainsString("PHP value '***'", $messages[0]);
        static::assertStringContainsString('Expected format "JSON"', $messages[1]);
        static::assertStringContainsString('Expected format "JSON object or array"', $messages[2]);
        static::assertStringContainsString('Expected format "JSON object or array"', $messages[3]);
        static::assertStringNotContainsString('{not json', $messages[1]);
        static::assertStringContainsString('database value "***"', $messages[1]);
        static::assertStringContainsString('database value "***"', $messages[2]);
        static::assertStringContainsString('database value "***"', $messages[3]);
    }

    public function testAJsonDocumentIsDeclaredAsAJsonColumn(): void
    {
        foreach ([SubmissionSnapshotType::NAME, SubmissionErrorsType::NAME] as $name) {
            static::assertSame(
                $this->platform->getJsonTypeDeclarationSQL([]),
                Type::getType($name)->getSQLDeclaration([], $this->platform),
            );
        }
    }

    public function testItRefusesAListThatIsNotAListOfSubmissionErrors(): void
    {
        $type = Type::getType(SubmissionErrorsType::NAME);
        $error = SubmissionError::of('ERR_A', 'm');

        foreach ([['a' => $error], [$error, 'text'], [1 => $error]] as $value) {
            try {
                $type->convertToDatabaseValue($value, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                static::assertStringEndsWith('types: ' . self::ERRORS_TYPE . ', null.', $exception->getMessage());
            }
        }
    }

    public function testStoredContentThatTheDomainRefusesIsReportedWithoutEchoingIt(): void
    {
        $stored = [
            '{"rawStatus":"SECRET-status","rawPaymentStatus":null,"periods":[],' . self::EMPTY_FILES . ',"counters":{},"lastUpdateDate":"SECRET-instant"}',
            '{"rawStatus":null,"rawPaymentStatus":null,"periods":[],' . self::EMPTY_FILES . ',"counters":{"numberOfError":"SECRET-1e3"},"lastUpdateDate":null}',
            '{"rawStatus":null,"rawPaymentStatus":null,"periods":[],"reportFiles":{"globalReport":{"fileId":"SECRET/name","name":"SECRET-name"},'
            . '"detailedReport":null,"usersAnomaliesReport":null,"affiliatedUsersFile":null},"counters":{},"lastUpdateDate":null}',
        ];
        foreach ($stored as $document) {
            try {
                Type::getType(SubmissionSnapshotType::NAME)->convertToPHPValue($document, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
                static::assertStringContainsString('database value "***"', $exception->getMessage());
                static::assertStringContainsString('Expected format "' . SubmissionSnapshot::class . '"', $exception->getMessage());
                static::assertStringNotContainsString('SECRET', $exception->getMessage());
            }
        }
    }

    /**
     * @return array{string, object|list<SubmissionError>, string, string} type name, value, expected JSON, description of the PHP type
     *
     * @throws InvalidValue
     */
    private static function document(string $case): array
    {
        return match ($case) {
            'snapshot-full' => [
                SubmissionSnapshotType::NAME,
                new SubmissionSnapshot(
                    'ACCEPTED',
                    'PROCESSING',
                    [ReportedPeriod::fromWire('2026-01'), ReportedPeriod::fromWire('2026-02')],
                    ReportFiles::of(
                        FileReference::of('file-1', 'global-report.csv'),
                        FileReference::of('file-2', null),
                        FileReference::of('file-3', 'anomalies.csv'),
                        FileReference::of('file-4', 'users.csv'),
                    ),
                    ['numberOfError' => DecimalNumber::fromInt(0), 'numberOfDeclaredUser' => DecimalNumber::fromString('12')],
                    Instant::fromIso8601('2026-03-01T10:00:00Z'),
                ),
                '{"rawStatus":"ACCEPTED","rawPaymentStatus":"PROCESSING","periods":["2026-01","2026-02"],"reportFiles":{'
                . '"globalReport":{"fileId":"file-1","name":"global-report.csv"},"detailedReport":{"fileId":"file-2","name":null},'
                . '"usersAnomaliesReport":{"fileId":"file-3","name":"anomalies.csv"},"affiliatedUsersFile":{"fileId":"file-4","name":"users.csv"}},'
                . '"counters":{"numberOfError":"0","numberOfDeclaredUser":"12"},"lastUpdateDate":"2026-03-01T10:00:00Z"}',
                SubmissionSnapshot::class,
            ],
            'snapshot-nulls' => [
                SubmissionSnapshotType::NAME,
                new SubmissionSnapshot(null, null, [], ReportFiles::none(), [], null),
                '{"rawStatus":null,"rawPaymentStatus":null,"periods":[],' . self::EMPTY_FILES . ',"counters":[],"lastUpdateDate":null}',
                SubmissionSnapshot::class,
            ],
            'errors' => [
                SubmissionErrorsType::NAME,
                [SubmissionError::of('ERR_FORMAT', 'First message.'), SubmissionError::of(null, null)],
                '[{"code":"ERR_FORMAT","message":"First message."},{"code":null,"message":null}]',
                self::ERRORS_TYPE,
            ],
            default => [SubmissionErrorsType::NAME, [], '[]', self::ERRORS_TYPE],
        };
    }
}
