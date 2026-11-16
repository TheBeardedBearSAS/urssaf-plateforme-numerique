<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\IntegrationEventTranslator;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\DeclarationPaymentStatusChanged;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionAccepted;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionNotReceived;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionOutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploadRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionKind;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\DomainEventSamples as Samples;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\SequentialIdGenerator;

final class IntegrationEventTranslatorTest extends TestCase
{
    private const DECLARATION = [
        'tenantId' => 'tenant-1',
        'aggregateId' => 'sub-1',
        'externalReference' => 'ref-1',
        'kind' => 'declaration',
        'declarationType' => 'REGULARISATION',
        'fileName' => 'file-1.zip',
    ];

    public function testUploadedAndOutcomeUnknownAndNotReceived(): void
    {
        self::assertTranslated(
            SubmissionUploaded::class,
            [...self::DECLARATION, 'occurredAt' => '2026-01-15T10:00:00Z', 'urssafRef' => 'urssaf-1'],
            Samples::uploaded(),
        );
        self::assertTranslated(
            SubmissionOutcomeUnknown::class,
            [...self::DECLARATION, 'occurredAt' => '2026-01-15T10:00:00Z', 'urssafRef' => null],
            Samples::outcomeUnknown(),
        );
        self::assertTranslated(
            SubmissionNotReceived::class,
            [...self::DECLARATION, 'occurredAt' => '2026-01-16T08:30:00Z', 'urssafRef' => null],
            Samples::notReceived(),
        );
    }

    public function testAnIdentificationHasNoDeclarationType(): void
    {
        $identification = [
            ...self::DECLARATION,
            'kind' => 'identification',
            'declarationType' => null,
            'occurredAt' => '2026-01-15T10:00:00Z',
            'urssafRef' => 'urssaf-1',
        ];

        self::assertTranslated(SubmissionUploaded::class, $identification, Samples::uploaded(SubmissionKind::Identification));
    }

    public function testNoEventOfAnIdentificationHasADeclarationType(): void
    {
        $identification = SubmissionKind::Identification;
        $events = [
            Samples::uploaded($identification),
            Samples::uploadRejected([], $identification),
            Samples::outcomeUnknown($identification),
            Samples::notReceived($identification),
            Samples::accepted(ReportFiles::none(), $identification),
            Samples::rejected(ReportFiles::none(), $identification),
            Samples::paymentStatusChangedOfAnIdentification(),
        ];

        foreach ($events as $event) {
            $translated = (new IntegrationEventTranslator(new SequentialIdGenerator()))->translate($event);

            static::assertCount(1, $translated);
            static::assertSame('identification', $translated[0]->toArray()['kind']);
            static::assertNull($translated[0]->toArray()['declarationType']);
        }
    }

    public function testTheUploadRejectionCarriesTheErrorCodes(): void
    {
        $errors = [SubmissionError::of('ERR_A', 'a'), SubmissionError::of(null, 'no code'), SubmissionError::of('ERR_B', null)];

        self::assertTranslated(
            SubmissionUploadRejected::class,
            [...self::DECLARATION, 'occurredAt' => '2026-01-15T10:00:00Z', 'urssafRef' => null, 'errorCodes' => ['ERR_A', 'ERR_B']],
            Samples::uploadRejected($errors),
        );
    }

    public function testTheErrorCodesAreAListWhateverTheirPositionInTheErrors(): void
    {
        $translated = (new IntegrationEventTranslator(new SequentialIdGenerator()))->translate(
            Samples::uploadRejected([SubmissionError::of(null, 'no code'), SubmissionError::of('ERR_B', null)]),
        );

        static::assertInstanceOf(SubmissionUploadRejected::class, $translated[0]);
        static::assertSame(['ERR_B'], $translated[0]->errorCodes);
        static::assertSame([0], array_keys($translated[0]->errorCodes));

        $withoutCodes = (new IntegrationEventTranslator(new SequentialIdGenerator()))->translate(
            Samples::uploadRejected([SubmissionError::of(null, 'no code')]),
        );

        static::assertInstanceOf(SubmissionUploadRejected::class, $withoutCodes[0]);
        static::assertSame([], $withoutCodes[0]->errorCodes);
    }

    public function testTheProcessingResultsCarryTheStatusAndTheReportFiles(): void
    {
        $reports = [
            ['field' => 'globalReportRef', 'fileId' => 'file-global', 'name' => 'global.pdf'],
            ['field' => 'usersAnomaliesReportRef', 'fileId' => 'file-anomalies', 'name' => null],
        ];
        $common = [...self::DECLARATION, 'occurredAt' => '2026-01-16T09:00:00Z', 'urssafRef' => 'urssaf-1'];

        self::assertTranslated(SubmissionAccepted::class, [...$common, 'status' => 'ACCEPTED', 'reports' => $reports], Samples::accepted(Samples::reports()));
        self::assertTranslated(SubmissionRejected::class, [...$common, 'status' => 'REJECTED', 'reports' => $reports], Samples::rejected(Samples::reports()));
        self::assertTranslated(SubmissionAccepted::class, [...$common, 'status' => 'ACCEPTED', 'reports' => []], Samples::accepted(ReportFiles::none()));
    }

    public function testThePaymentStatusChange(): void
    {
        $common = [...self::DECLARATION, 'occurredAt' => '2026-01-16T09:00:00Z', 'urssafRef' => 'urssaf-1'];

        self::assertTranslated(
            DeclarationPaymentStatusChanged::class,
            [...$common, 'previousPaymentStatus' => null, 'paymentStatus' => 'DECLARED'],
            Samples::paymentStatusChanged(null, 'DECLARED'),
        );
        self::assertTranslated(
            DeclarationPaymentStatusChanged::class,
            [...$common, 'previousPaymentStatus' => 'DECLARED', 'paymentStatus' => 'PROCESSED'],
            Samples::paymentStatusChanged('DECLARED', 'PROCESSED'),
        );
    }

    public function testTheTechnicalUploadFailuresHaveNoIntegrationEventAndConsumeNoId(): void
    {
        $ids = new SequentialIdGenerator();

        $translated = (new IntegrationEventTranslator($ids))->translate(
            Samples::uploadFailed(),
            Samples::uploaded(),
            Samples::uploadFailed(),
            Samples::outcomeUnknown(),
        );

        static::assertCount(2, $translated);
        static::assertInstanceOf(SubmissionUploaded::class, $translated[0]);
        static::assertInstanceOf(SubmissionOutcomeUnknown::class, $translated[1]);
        static::assertSame(['id-1', 'id-2'], array_map(static fn(IntegrationEvent $event): string => $event->eventId, $translated));
        static::assertSame('id-3', $ids->generate());
    }

    public function testNothingIsTranslatedWithoutEvents(): void
    {
        static::assertSame([], (new IntegrationEventTranslator(new SequentialIdGenerator()))->translate());
    }

    /**
     * @param class-string<IntegrationEvent> $class
     * @param array<string, mixed>           $expected the properties of the event, but its id
     */
    private static function assertTranslated(string $class, array $expected, DomainEvent $event): void
    {
        $translated = (new IntegrationEventTranslator(new SequentialIdGenerator()))->translate($event);

        static::assertCount(1, $translated);
        static::assertInstanceOf($class, $translated[0]);
        static::assertSame('id-1', $translated[0]->eventId);
        $properties = $translated[0]->toArray();
        unset($properties['eventId']);
        ksort($properties);
        ksort($expected);
        static::assertSame($expected, $properties);
    }
}
