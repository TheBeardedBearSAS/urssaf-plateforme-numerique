<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Benchmark;

use Nyholm\Psr7\Factory\Psr17Factory;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortOrder;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\MultipartBody;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\QueryEncoder;

/**
 * Encoding of what is sent to URSSAF: the query string of a declaration search (every parameter) and the
 * multipart body of an upload (official file name, 64 KiB fictitious payload).
 */
final readonly class WireEncodingBench
{
    private const PAYLOAD_SIZE = 65_536;

    private SubmissionSearch $search;

    private MultipartBody $multipart;

    private FileName $fileName;

    private string $payload;

    public function __construct()
    {
        $this->search = SubmissionSearch::of(
            PageRequest::of(
                2,
                10,
                SortOrder::of('period', SortDirection::Asc),
                SortOrder::of('lastUpdateDate', SortDirection::Desc),
                SortOrder::of('declarationFileName', SortDirection::Asc),
            ),
            'Platform X',
            CalendarDate::fromString('2025-04-01'),
            CalendarDate::fromString('2025-04-30'),
            DeclarationType::Regularisation,
        );
        $this->multipart = new MultipartBody(new Psr17Factory());
        $this->fileName = FileName::fromString('DPDE_202505_24001_000_20250428150823.xml.gz');
        $this->payload = str_repeat('0123456789abcdef', self::PAYLOAD_SIZE / 16);
    }

    public function benchEncodeADeclarationSearchQuery(): string
    {
        return QueryEncoder::toQueryString(QueryEncoder::declarationSearch($this->search));
    }

    public function benchBuildAMultipartUploadBody(): void
    {
        $content = fopen('php://temp', 'w+b');
        if ($content === false) {
            return;
        }

        fwrite($content, $this->payload);
        rewind($content);

        $this->multipart->build($this->fileName, $content, 'bench-boundary');

        fclose($content);
    }
}
