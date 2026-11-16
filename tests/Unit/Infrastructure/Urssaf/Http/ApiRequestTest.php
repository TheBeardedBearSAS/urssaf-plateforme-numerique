<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\StringFileSource;

final class ApiRequestTest extends TestCase
{
    public function testGetCarriesAnOrderedQueryAndNoFile(): void
    {
        $query = [['sort', 'period:ASC'], ['sort', 'lastUpdateDate:DESC']];

        $request = ApiRequest::get(Operation::SearchDeclarations, '/x', $query);

        static::assertSame(Operation::SearchDeclarations, $request->operation());
        static::assertSame('GET', $request->method());
        static::assertSame('/x', $request->path());
        static::assertSame($query, $request->query());
        static::assertNull($request->file());
        static::assertFalse($request->isDownload());
    }

    public function testMultipartPostsAFileWithAQuery(): void
    {
        $file = new StringFileSource(FileName::fromString('DPDE_1.xml.gz'), 'x');

        $request = ApiRequest::multipart(Operation::UploadDeclaration, '/y', [['type', 'DPDE']], $file);

        static::assertSame(Operation::UploadDeclaration, $request->operation());
        static::assertSame('POST', $request->method());
        static::assertSame('/y', $request->path());
        static::assertSame([['type', 'DPDE']], $request->query());
        static::assertSame($file, $request->file());
        static::assertFalse($request->isDownload());
    }

    public function testDownloadIsAGetOfABinaryWithoutQuery(): void
    {
        $request = ApiRequest::download(Operation::DownloadIdentificationFile, '/z');

        static::assertSame(Operation::DownloadIdentificationFile, $request->operation());
        static::assertSame('GET', $request->method());
        static::assertSame('/z', $request->path());
        static::assertSame([], $request->query());
        static::assertNull($request->file());
        static::assertTrue($request->isDownload());
    }
}
