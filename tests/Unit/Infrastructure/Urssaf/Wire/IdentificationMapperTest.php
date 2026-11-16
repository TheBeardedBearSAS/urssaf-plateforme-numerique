<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileStatus;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\IdentificationMapper;

final class IdentificationMapperTest extends TestCase
{
    public function testItMapsAFullIdentification(): void
    {
        $identification = IdentificationMapper::map(
            WireFixtures::fixture('identification_response_handbuilt'),
            new DateTimeZone('Europe/Paris'),
        );

        static::assertSame('cd48ba8a-3a7e-4b80-8e87-f14a5542c6cf', $identification->identificationFileRef());
        static::assertSame('DPID_202505_24001_000_20250428150823.xml.gz', $identification->identificationFileName());
        $period = $identification->period();
        static::assertNotNull($period);
        static::assertSame('2025-05', $period->raw());
        static::assertSame('2025-05', $period->period()?->value());
        static::assertSame('cd48ba8a-3a7e-4b80-8e87-f14a5542c6cf', $identification->urssafRef()?->value());
        static::assertSame('user-0001', $identification->uploader()->userId());
        static::assertSame('uploader@example.com', $identification->uploader()->userEmail());
        static::assertSame('ACCEPTED', $identification->processingStatus()->raw());
        static::assertSame(FileStatus::Accepted, $identification->processingStatus()->status());
        static::assertSame('File successfully Processed', $identification->processingStatus()->label());
        // 20:04:14 in Paris (UTC+2 on this date).
        static::assertSame('2025-04-28T18:04:14Z', $identification->dates()->lastUpdateDate()?->format());
        $uploadDate = $identification->dates()->uploadDate();
        static::assertNotNull($uploadDate);
        static::assertSame('2025-04-28T20:03:51', $uploadDate->raw());
        static::assertSame('2025-04-28T18:03:51Z', $uploadDate->instant()?->format());
        static::assertSame('2025-04-28T20:04:14', $identification->dates()->treatmentDate()?->raw());
        static::assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $identification->reportFiles()->globalReport()?->fileId());
        static::assertSame('d4e5f6a7-b8c9-0123-def1-234567890123', $identification->reportFiles()->affiliatedUsersFile()?->fileId());
        $details = $identification->details();
        static::assertNotNull($details);
        static::assertSame('12', $details->numberOfDeclaredUser()?->toString());
        static::assertSame('10', $details->numberOfIdentifiedUser()?->toString());
        static::assertSame('2', $details->numberOfError()?->toString());
    }

    public function testTheApiTimeZoneDrivesDatesWithoutOffset(): void
    {
        $utc = IdentificationMapper::map(
            WireFixtures::payload(['lastUpdateDate' => '2025-04-28T20:04:14']),
            new DateTimeZone('UTC'),
        );
        $explicit = IdentificationMapper::map(
            WireFixtures::payload(['lastUpdateDate' => '2025-04-28T20:04:14+05:00']),
            new DateTimeZone('UTC'),
        );

        static::assertSame('2025-04-28T20:04:14Z', $utc->dates()->lastUpdateDate()?->format());
        static::assertSame('2025-04-28T15:04:14Z', $explicit->dates()->lastUpdateDate()?->format());
    }

    public function testEveryFieldIsOptional(): void
    {
        $identification = IdentificationMapper::map(WireFixtures::payload([]), new DateTimeZone('UTC'));

        static::assertNull($identification->identificationFileRef());
        static::assertNull($identification->identificationFileName());
        static::assertNull($identification->period());
        static::assertNull($identification->urssafRef());
        static::assertNull($identification->uploader()->userId());
        static::assertNull($identification->uploader()->userEmail());
        static::assertNull($identification->processingStatus()->raw());
        static::assertNull($identification->processingStatus()->label());
        static::assertNull($identification->dates()->lastUpdateDate());
        static::assertNull($identification->dates()->uploadDate());
        static::assertNull($identification->dates()->treatmentDate());
        static::assertSame([], $identification->reportFiles()->all());
        static::assertNull($identification->details());
    }

    public function testAnUnexpectedStatusOrPeriodNeverBreaksTheRead(): void
    {
        $identification = IdentificationMapper::map(
            WireFixtures::payload(['status' => 'SOMETHING_NEW', 'period' => 'May 2025', 'uploadDate' => 'yesterday']),
            new DateTimeZone('UTC'),
        );

        static::assertSame('SOMETHING_NEW', $identification->processingStatus()->raw());
        static::assertNull($identification->processingStatus()->status());
        $period = $identification->period();
        static::assertNotNull($period);
        static::assertSame('May 2025', $period->raw());
        static::assertNull($period->period());
        $uploadDate = $identification->dates()->uploadDate();
        static::assertNotNull($uploadDate);
        static::assertSame('yesterday', $uploadDate->raw());
        static::assertNull($uploadDate->instant());
    }

    public function testEmptyDetailsGiveNullCounters(): void
    {
        $details = IdentificationMapper::map(WireFixtures::payload(['details' => []]), new DateTimeZone('UTC'))->details();

        static::assertNotNull($details);
        static::assertNull($details->numberOfDeclaredUser());
        static::assertNull($details->numberOfIdentifiedUser());
        static::assertNull($details->numberOfError());
    }

    public function testMapListReadsASearchResult(): void
    {
        $items = IdentificationMapper::mapList(
            WireFixtures::fixture('search_identifications_response_handbuilt'),
            new DateTimeZone('UTC'),
        );

        static::assertCount(2, $items);
        static::assertSame(FileStatus::Accepted, $items[0]->processingStatus()->status());
        static::assertSame('0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0', $items[1]->urssafRef()?->value());
        static::assertSame(FileStatus::InProcess, $items[1]->processingStatus()->status());
        static::assertNull($items[1]->details());
    }

    public function testMapListOfAnEmptyArray(): void
    {
        static::assertSame([], IdentificationMapper::mapList(WireFixtures::payload([]), new DateTimeZone('UTC')));
    }

    public function testMapListRejectsAnObject(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        IdentificationMapper::mapList(WireFixtures::payload(['a' => 1]), new DateTimeZone('UTC'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidIdentifications(): iterable
    {
        yield 'file ref not a string' => [['identificationFileRef' => 1]];
        yield 'file name not a string' => [['identificationFileName' => []]];
        yield 'period is an array' => [['period' => ['2025-05']]];
        yield 'urssafRef not a string' => [['urssafRef' => 5]];
        yield 'urssafRef empty' => [['urssafRef' => '']];
        yield 'urssafRef with a slash' => [['urssafRef' => 'a/b']];
        yield 'userId not a string' => [['userId' => 1]];
        yield 'userEmail not a string' => [['userEmail' => true]];
        yield 'status not a string' => [['status' => 1]];
        yield 'statusLabel not a string' => [['statusLabel' => 1]];
        yield 'lastUpdateDate not a string' => [['lastUpdateDate' => 1_745_870_654]];
        yield 'lastUpdateDate unparseable' => [['lastUpdateDate' => 'yesterday']];
        yield 'lastUpdateDate impossible' => [['lastUpdateDate' => '2025-02-30T10:00:00']];
        yield 'uploadDate not a string' => [['uploadDate' => 1]];
        yield 'treatmentDate not a string' => [['treatmentDate' => 1]];
        yield 'report is a string' => [['globalReportRef' => 'x']];
        yield 'details is a string' => [['details' => 'x']];
        yield 'counter is a boolean' => [['details' => ['numberOfError' => true]]];
        yield 'counter is text' => [['details' => ['numberOfError' => '2']]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidIdentifications')]
    public function testItRejectsWronglyTypedFields(array $body): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        IdentificationMapper::map(WireFixtures::payload($body), new DateTimeZone('UTC'));
    }
}
