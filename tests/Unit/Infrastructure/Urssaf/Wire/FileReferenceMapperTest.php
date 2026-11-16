<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\FileReferenceMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\ReportFilesMapper;

final class FileReferenceMapperTest extends TestCase
{
    public function testItReadsANamedReference(): void
    {
        $reference = FileReferenceMapper::field(
            WireFixtures::payload(['globalReportRef' => ['name' => 'report.xml', 'fileId' => 'abc']]),
            'globalReportRef',
        );

        static::assertNotNull($reference);
        static::assertSame('abc', $reference->fileId());
        static::assertSame('report.xml', $reference->name());
    }

    public function testTheNameIsOptional(): void
    {
        $reference = FileReferenceMapper::field(WireFixtures::payload(['r' => ['fileId' => 'abc']]), 'r');

        static::assertNotNull($reference);
        static::assertNull($reference->name());
    }

    public function testAMissingFieldOrFileIdGivesNoReference(): void
    {
        foreach ([[], ['r' => null], ['r' => []], ['r' => ['name' => 'x']], ['r' => ['fileId' => null]], ['r' => ['fileId' => '']]] as $body) {
            static::assertNull(FileReferenceMapper::field(WireFixtures::payload($body), 'r'));
        }
    }

    public function testItRejectsWronglyTypedContent(): void
    {
        foreach ([['r' => 'text'], ['r' => ['fileId' => 12]], ['r' => ['fileId' => 'a', 'name' => 3]]] as $body) {
            $rejected = false;

            try {
                FileReferenceMapper::field(WireFixtures::payload($body), 'r');
            } catch (UnexpectedUrssafResponse) {
                $rejected = true;
            }

            static::assertTrue($rejected);
        }
    }

    public function testItRejectsAFileIdThatCannotBeUsedInAPath(): void
    {
        try {
            FileReferenceMapper::field(WireFixtures::payload(['r' => ['fileId' => 'a/b']]), 'r');
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertStringContainsString('field "r.fileId" is not a valid reference', $exception->getMessage());
            static::assertNotNull($exception->getPrevious());
        }
    }

    public function testReportFilesReadsTheFourWireKeys(): void
    {
        $files = ReportFilesMapper::map(WireFixtures::fixture('identification_response_handbuilt'));

        static::assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $files->globalReport()?->fileId());
        static::assertSame('b2c3d4e5-f6a7-8901-bcde-f12345678901', $files->detailedReport()?->fileId());
        static::assertSame('c3d4e5f6-a7b8-9012-cdef-123456789012', $files->usersAnomaliesReport()?->fileId());
        static::assertSame('d4e5f6a7-b8c9-0123-def1-234567890123', $files->affiliatedUsersFile()?->fileId());
    }

    public function testReportFilesAreNullWhenAbsent(): void
    {
        $files = ReportFilesMapper::map(WireFixtures::payload([]));

        static::assertSame([], $files->all());
    }
}
