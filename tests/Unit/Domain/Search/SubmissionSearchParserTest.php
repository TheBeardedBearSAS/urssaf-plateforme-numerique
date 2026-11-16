<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Search;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SortDirection;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearchDraft;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearchParser;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\Violation;

final class SubmissionSearchParserTest extends TestCase
{
    public function testParsesAValidDraft(): void
    {
        $draft = new SubmissionSearchDraft(
            2,
            50,
            ['period:ASC', 'lastUpdateDate:DESC'],
            'Platform X',
            '2025-01-01',
            '2025-01-31',
            'REGULARISATION',
        );
        $parser = new SubmissionSearchParser();

        $search = $parser->parse($draft);

        static::assertSame(2, $search->pageRequest()->page());
        static::assertSame(50, $search->pageRequest()->size());
        static::assertCount(2, $search->pageRequest()->sorts());
        static::assertSame('period', $search->pageRequest()->sorts()[0]->field());
        static::assertSame(SortDirection::Asc, $search->pageRequest()->sorts()[0]->direction());
        static::assertSame('lastUpdateDate:DESC', $search->pageRequest()->sorts()[1]->toWire());
        static::assertSame('Platform X', $search->search());
        static::assertSame('2025-01-01', $search->startDate()?->format());
        static::assertSame('2025-01-31', $search->endDate()?->format());
        static::assertSame(DeclarationType::Regularisation, $search->type());
        static::assertTrue($parser->validate($draft)->isValid());
        static::assertSame([], $parser->validate($draft)->all());
    }

    public function testParsesAnEmptyDraft(): void
    {
        $parser = new SubmissionSearchParser();
        $draft = new SubmissionSearchDraft(null, null, [], null, null, null, null);

        $search = $parser->parse($draft);

        static::assertNull($search->pageRequest()->page());
        static::assertNull($search->search());
        static::assertNull($search->startDate());
        static::assertNull($search->endDate());
        static::assertNull($search->type());
        static::assertEquals(ValidationResult::valid(), $parser->validate($draft));
    }

    public function testReportsEveryViolationAtOnce(): void
    {
        $parser = new SubmissionSearchParser();
        $draft = new SubmissionSearchDraft(
            0,
            501,
            ['period:ASC', 'period', 'a b:DESC'],
            '',
            '2025-02-30',
            'yesterday',
            'OTHER',
        );

        $result = $parser->validate($draft);

        $found = [];
        foreach ($result->errors() as $violation) {
            $found[] = $violation->path() . ':' . $violation->code();
        }
        static::assertSame(
            [
                'sort[1]:sort_order.invalid_format',
                'sort[2]:sort_order.invalid_field',
                'page:page_request.page_not_positive',
                'size:page_request.size_out_of_range',
                'startDate:calendar_date.invalid_date',
                'endDate:calendar_date.invalid_format',
                'type:submission_search.type_invalid',
                'search:submission_search.search_empty',
            ],
            $found,
        );
        static::assertSame($result->all(), $result->errors());
    }

    public function testParseThrowsWithTheSameResult(): void
    {
        $parser = new SubmissionSearchParser();
        $draft = new SubmissionSearchDraft(0, null, [], null, null, null, 'OTHER');

        try {
            $parser->parse($draft);
            static::fail('The draft must be refused.');
        } catch (ValidationFailed $validationFailed) {
            static::assertEquals($parser->validate($draft), $validationFailed->result());
            static::assertCount(2, $validationFailed->result()->errors());
        }
    }

    public function testEachFieldIsReportedAlone(): void
    {
        $parser = new SubmissionSearchParser();

        static::assertSame(
            ['startDate'],
            $this->paths($parser->validate(new SubmissionSearchDraft(null, null, [], null, 'x', null, null))),
        );
        static::assertSame(
            ['endDate'],
            $this->paths($parser->validate(new SubmissionSearchDraft(null, null, [], null, null, 'x', null))),
        );
        static::assertSame(
            ['type'],
            $this->paths($parser->validate(new SubmissionSearchDraft(null, null, [], null, null, null, 'declaration'))),
        );
        static::assertSame(
            ['search'],
            $this->paths($parser->validate(new SubmissionSearchDraft(null, null, [], str_repeat('a', 256), null, null, null))),
        );
        static::assertSame(
            ['sort[0]'],
            $this->paths($parser->validate(new SubmissionSearchDraft(null, null, ['x'], null, null, null, null))),
        );
    }

    /**
     * @return list<string>
     */
    private function paths(ValidationResult $result): array
    {
        return array_map(static fn(Violation $violation): string => $violation->path(), $result->errors());
    }
}
