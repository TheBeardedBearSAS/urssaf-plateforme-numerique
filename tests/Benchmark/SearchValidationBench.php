<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Benchmark;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearchDraft;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearchParser;

/**
 * Local validation of the search criteria (paging, sorts, keyword, dates, type) before any call to URSSAF.
 */
final readonly class SearchValidationBench
{
    private SubmissionSearchParser $parser;

    private SubmissionSearchDraft $validDraft;

    private SubmissionSearchDraft $invalidDraft;

    public function __construct()
    {
        $this->parser = new SubmissionSearchParser();
        $this->validDraft = new SubmissionSearchDraft(
            2,
            50,
            ['period:ASC', 'lastUpdateDate:DESC'],
            'Platform X',
            '2025-01-01',
            '2025-01-31',
            'REGULARISATION',
        );
        $this->invalidDraft = new SubmissionSearchDraft(
            0,
            501,
            ['period:ASC', 'period', 'a b:DESC'],
            '',
            '2025-02-30',
            'yesterday',
            'OTHER',
        );
    }

    public function benchValidateASearchDraft(): void
    {
        $this->parser->parse($this->validDraft);
    }

    public function benchRejectAnInvalidDraft(): void
    {
        $this->parser->validate($this->invalidDraft);
    }
}
