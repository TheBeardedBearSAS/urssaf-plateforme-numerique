<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\PaymentStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\DeclarationMapper;

final class DeclarationMapperTest extends TestCase
{
    public function testItMapsAFullDeclaration(): void
    {
        $declaration = DeclarationMapper::map(
            WireFixtures::fixture('declaration_response_handbuilt'),
            new DateTimeZone('Europe/Paris'),
        );

        static::assertSame('cd48ba8a-3a7e-4b80-8e87-f14a5542c6cf', $declaration->declarationFileRef());
        static::assertSame('DPRE_202505_24001_000_20250428150823.xml.gz', $declaration->declarationFileName());
        static::assertSame('REGULARISATION', $declaration->rawDeclarationType());
        static::assertSame(DeclarationType::Regularisation, $declaration->declarationType());
        static::assertCount(2, $declaration->periods());
        static::assertSame('2025-04', $declaration->periods()[0]->raw());
        static::assertSame('2025-05', $declaration->periods()[1]->period()?->value());
        static::assertSame('unique-file-submission-id-12345', $declaration->urssafRef()?->value());
        static::assertSame('user-0001', $declaration->uploader()->userId());
        static::assertSame('uploader@example.com', $declaration->uploader()->userEmail());
        static::assertSame(FileStatus::Accepted, $declaration->processingStatus()->status());
        static::assertSame('File successfully Processed', $declaration->processingStatus()->label());
        static::assertSame('PROCESSED', $declaration->paymentState()->raw());
        static::assertSame(PaymentStatus::Processed, $declaration->paymentState()->status());
        static::assertSame('Payment processed', $declaration->paymentState()->label());
        static::assertSame('2025-04-28T18:04:14Z', $declaration->dates()->lastUpdateDate()?->format());
        static::assertSame('2025-04-28T20:03:51', $declaration->dates()->uploadDate()?->raw());
        static::assertSame('2025-04-28T20:04:14', $declaration->dates()->treatmentDate()?->raw());
        static::assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $declaration->reportFiles()->globalReport()?->fileId());
        static::assertSame('b2c3d4e5-f6a7-8901-bcde-f12345678901', $declaration->reportFiles()->detailedReport()?->fileId());
        static::assertSame('c3d4e5f6-a7b8-9012-cdef-123456789012', $declaration->reportFiles()->usersAnomaliesReport()?->fileId());
        static::assertNull($declaration->reportFiles()->affiliatedUsersFile());
        $statistics = $declaration->statistics();
        static::assertNotNull($statistics);
        static::assertSame('12', $statistics->numberOfDeclaredUser()?->toString());
        static::assertSame('15230.5', $statistics->totalRevenue()?->toString());
        static::assertSame('3350.71', $statistics->debitAmount()?->toString());
        static::assertSame('3350.71', $statistics->paymentAmount()?->toString());
        static::assertSame('0', $statistics->numberOfError()?->toString());
        static::assertSame('DPRE_202504_24001_000_20250328150823.xml.gz', $declaration->concernedRegularisation());
    }

    public function testEveryFieldIsOptional(): void
    {
        $declaration = DeclarationMapper::map(WireFixtures::payload([]), new DateTimeZone('UTC'));

        static::assertNull($declaration->declarationFileRef());
        static::assertNull($declaration->declarationFileName());
        static::assertNull($declaration->rawDeclarationType());
        static::assertNull($declaration->declarationType());
        static::assertSame([], $declaration->periods());
        static::assertNull($declaration->urssafRef());
        static::assertNull($declaration->uploader()->userId());
        static::assertNull($declaration->processingStatus()->raw());
        static::assertNull($declaration->paymentState()->raw());
        static::assertNull($declaration->paymentState()->label());
        static::assertNull($declaration->dates()->lastUpdateDate());
        static::assertSame([], $declaration->reportFiles()->all());
        static::assertNull($declaration->statistics());
        static::assertNull($declaration->concernedRegularisation());
    }

    public function testAnUnexpectedTypeOrPaymentStatusNeverBreaksTheRead(): void
    {
        $declaration = DeclarationMapper::map(
            WireFixtures::payload(['declarationType' => 'NEW_TYPE', 'paymentStatus' => 'NEW_STATUS', 'period' => ['soon']]),
            new DateTimeZone('UTC'),
        );

        static::assertSame('NEW_TYPE', $declaration->rawDeclarationType());
        static::assertNull($declaration->declarationType());
        static::assertSame('NEW_STATUS', $declaration->paymentState()->raw());
        static::assertNull($declaration->paymentState()->status());
        static::assertSame('soon', $declaration->periods()[0]->raw());
        static::assertNull($declaration->periods()[0]->period());
    }

    public function testEmptyStatisticsGiveNullCounters(): void
    {
        $statistics = DeclarationMapper::map(WireFixtures::payload(['statistics' => []]), new DateTimeZone('UTC'))->statistics();

        static::assertNotNull($statistics);
        static::assertNull($statistics->numberOfDeclaredUser());
        static::assertNull($statistics->totalRevenue());
        static::assertNull($statistics->debitAmount());
        static::assertNull($statistics->paymentAmount());
        static::assertNull($statistics->numberOfError());
    }

    public function testMapListReadsASearchResult(): void
    {
        $items = DeclarationMapper::mapList(
            WireFixtures::fixture('search_declarations_response_handbuilt'),
            new DateTimeZone('UTC'),
        );

        static::assertCount(2, $items);
        static::assertSame(PaymentStatus::Declared, $items[0]->paymentState()->status());
        static::assertSame('0', $items[0]->statistics()?->paymentAmount()?->toString());
        static::assertSame('unique-file-submission-id-67890', $items[1]->urssafRef()?->value());
        static::assertSame(PaymentStatus::NotInitiated, $items[1]->paymentState()->status());
        static::assertSame(FileStatus::InProcess, $items[1]->processingStatus()->status());
        static::assertNull($items[1]->statistics());
    }

    public function testMapListOfAnEmptyArray(): void
    {
        static::assertSame([], DeclarationMapper::mapList(WireFixtures::payload([]), new DateTimeZone('UTC')));
    }

    public function testMapListRejectsAnObject(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        DeclarationMapper::mapList(WireFixtures::payload(['a' => 1]), new DateTimeZone('UTC'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidDeclarations(): iterable
    {
        yield 'file ref not a string' => [['declarationFileRef' => 1]];
        yield 'file name not a string' => [['declarationFileName' => 1]];
        yield 'type not a string' => [['declarationType' => 1]];
        yield 'period is a string' => [['period' => '2025-05']];
        yield 'period contains a number' => [['period' => ['2025-05', 5]]];
        yield 'urssafRef not a string' => [['urssafRef' => 5]];
        yield 'urssafRef with a slash' => [['urssafRef' => 'a/b']];
        yield 'userId not a string' => [['userId' => 1]];
        yield 'status not a string' => [['status' => 1]];
        yield 'paymentStatus not a string' => [['paymentStatus' => 1]];
        yield 'paymentStatusLabel not a string' => [['paymentStatusLabel' => 1]];
        yield 'lastUpdateDate unparseable' => [['lastUpdateDate' => 'yesterday']];
        yield 'report is a list' => [['detailedReportRef' => ['x']]];
        yield 'statistics is a string' => [['statistics' => 'x']];
        yield 'amount is text' => [['statistics' => ['totalRevenue' => 'a lot']]];
        yield 'amount is too large' => [['statistics' => ['totalRevenue' => 1.0e20]]];
        yield 'concernedRegularisation not a string' => [['concernedRegularisation' => 1]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidDeclarations')]
    public function testItRejectsWronglyTypedFields(array $body): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        DeclarationMapper::map(WireFixtures::payload($body), new DateTimeZone('UTC'));
    }
}
