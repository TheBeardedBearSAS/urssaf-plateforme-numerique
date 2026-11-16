<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Domain\Search;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;

final class SubmissionSearchTest extends TestCase
{
    public function testOf(): void
    {
        $request = PageRequest::of(1, 10);
        $start = CalendarDate::fromString('2025-01-01');
        $end = CalendarDate::fromString('2024-01-01');

        $search = SubmissionSearch::of($request, 'Platform X', $start, $end, DeclarationType::Declaration);

        static::assertSame($request, $search->pageRequest());
        static::assertSame('Platform X', $search->search());
        static::assertSame($start, $search->startDate());
        static::assertSame($end, $search->endDate());
        static::assertSame(DeclarationType::Declaration, $search->type());
    }

    public function testMaximumLengthIsAccepted(): void
    {
        $keyword = str_repeat('a', 255);

        static::assertSame($keyword, SubmissionSearch::of(PageRequest::firstPage(), $keyword, null, null, null)->search());
    }

    public function testAll(): void
    {
        $search = SubmissionSearch::all();

        static::assertNull($search->pageRequest()->page());
        static::assertNull($search->pageRequest()->size());
        static::assertSame([], $search->pageRequest()->sorts());
        static::assertNull($search->search());
        static::assertNull($search->startDate());
        static::assertNull($search->endDate());
        static::assertNull($search->type());
    }

    public function testEmptyKeywordIsRefused(): void
    {
        try {
            SubmissionSearch::of(PageRequest::firstPage(), '', null, null, null);
            static::fail('The keyword must be refused.');
        } catch (ValidationFailed $validationFailed) {
            $errors = $validationFailed->result()->errors();
            static::assertCount(1, $errors);
            static::assertSame('search', $errors[0]->path());
            static::assertSame('submission_search.search_empty', $errors[0]->code());
        }
    }

    public function testTooLongKeywordIsRefused(): void
    {
        try {
            SubmissionSearch::of(PageRequest::firstPage(), str_repeat('a', 256), null, null, null);
            static::fail('The keyword must be refused.');
        } catch (ValidationFailed $validationFailed) {
            $errors = $validationFailed->result()->errors();
            static::assertCount(1, $errors);
            static::assertSame('search', $errors[0]->path());
            static::assertSame('submission_search.search_too_long', $errors[0]->code());
            static::assertSame(['max_length' => 255], $errors[0]->params());
            static::assertSame('The search keyword must not exceed 255 characters.', $errors[0]->message());
        }
    }

    public function testTheKeywordLimitCountsCharactersNotBytes(): void
    {
        $search = SubmissionSearch::of(PageRequest::firstPage(), str_repeat('é', 255), null, null, null);

        static::assertSame(255, mb_strlen((string) $search->search()));

        $this->expectException(ValidationFailed::class);

        SubmissionSearch::of(PageRequest::firstPage(), str_repeat('é', 256), null, null, null);
    }
}
