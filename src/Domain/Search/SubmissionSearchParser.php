<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\Search;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\Validator;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Validation\Violation;

/**
 * Validates and parses raw search criteria, reporting every violation at once (notification pattern).
 *
 * Paths: page, size, sort[n], search, startDate, endDate, type.
 *
 * @implements Validator<SubmissionSearchDraft>
 */
final readonly class SubmissionSearchParser implements Validator
{
    /**
     * @param SubmissionSearchDraft $subject
     */
    public function validate(object $subject): ValidationResult
    {
        try {
            $this->parse($subject);
        } catch (ValidationFailed $validationFailed) {
            return $validationFailed->result();
        }

        return ValidationResult::valid();
    }

    /**
     * @throws ValidationFailed listing every violation
     */
    public function parse(SubmissionSearchDraft $draft): SubmissionSearch
    {
        $result = ValidationResult::valid();
        $sorts = [];
        foreach ($draft->sort as $index => $raw) {
            try {
                $sorts[] = SortOrder::fromWire($raw);
            } catch (InvalidValue $invalidValue) {
                $result = $result->with($invalidValue->toViolation(\sprintf('sort[%d]', $index)));
            }
        }
        $pageRequest = PageRequest::firstPage();
        try {
            $pageRequest = PageRequest::of($draft->page, $draft->size, ...$sorts);
        } catch (ValidationFailed $validationFailed) {
            $result = $result->merge($validationFailed->result());
        }
        $startDate = null;
        $endDate = null;
        try {
            $startDate = $draft->startDate === null ? null : CalendarDate::fromString($draft->startDate);
        } catch (InvalidValue $invalidValue) {
            $result = $result->with($invalidValue->toViolation('startDate'));
        }
        try {
            $endDate = $draft->endDate === null ? null : CalendarDate::fromString($draft->endDate);
        } catch (InvalidValue $invalidValue) {
            $result = $result->with($invalidValue->toViolation('endDate'));
        }
        $type = DeclarationType::fromCode($draft->type);
        if ($draft->type !== null && !$type instanceof DeclarationType) {
            $result = $result->with(Violation::error(
                'type',
                'submission_search.type_invalid',
                'The type must be DECLARATION or REGULARISATION.',
            ));
        }
        $search = SubmissionSearch::all();
        try {
            $search = SubmissionSearch::of($pageRequest, $draft->search, $startDate, $endDate, $type);
        } catch (ValidationFailed $validationFailed) {
            $result = $result->merge($validationFailed->result());
        }
        $result->throwIfInvalid();

        return $search;
    }
}
