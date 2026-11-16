<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\DeclarationPaymentStatusChanged;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\IntegrationEventCodec;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionAccepted;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionNotReceived;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionOutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploaded;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionUploadRejected;

final class IntegrationEventTest extends TestCase
{
    /**
     * @return iterable<string, array{IntegrationEvent, string, array<string, mixed>}>
     */
    public static function events(): iterable
    {
        $common = [
            'eventId' => 'event-1',
            'occurredAt' => '2026-03-01T10:00:00Z',
            'tenantId' => 'tenant-1',
            'aggregateId' => 'local-1',
            'externalReference' => 'ref-42',
            'kind' => 'declaration',
            'declarationType' => 'DECLARATION',
            'fileName' => 'file.xml',
            'urssafRef' => 'urssaf-1',
        ];
        $reports = [['field' => 'globalReportRef', 'fileId' => 'file-1', 'name' => null]];

        yield 'uploaded' => [self::uploaded(), 'urssaf.pn.submission.uploaded', $common];
        yield 'upload rejected' => [
            new SubmissionUploadRejected('event-1', '2026-03-01T10:00:00Z', 'tenant-1', 'local-1', 'ref-42', 'declaration', 'DECLARATION', 'file.xml', 'urssaf-1', ['E1']),
            'urssaf.pn.submission.upload_rejected',
            $common + ['errorCodes' => ['E1']],
        ];
        yield 'outcome unknown' => [
            new SubmissionOutcomeUnknown('event-1', '2026-03-01T10:00:00Z', 'tenant-1', 'local-1', 'ref-42', 'declaration', 'DECLARATION', 'file.xml', 'urssaf-1'),
            'urssaf.pn.submission.outcome_unknown',
            $common,
        ];
        yield 'not received' => [
            new SubmissionNotReceived('event-1', '2026-03-01T10:00:00Z', 'tenant-1', 'local-1', 'ref-42', 'declaration', 'DECLARATION', 'file.xml', 'urssaf-1'),
            'urssaf.pn.submission.not_received',
            $common,
        ];
        yield 'accepted' => [
            new SubmissionAccepted('event-1', '2026-03-01T10:00:00Z', 'tenant-1', 'local-1', 'ref-42', 'declaration', 'DECLARATION', 'file.xml', 'urssaf-1', 'ACCEPTED', $reports),
            'urssaf.pn.submission.accepted',
            $common + ['status' => 'ACCEPTED', 'reports' => $reports],
        ];
        yield 'rejected' => [
            new SubmissionRejected('event-1', '2026-03-01T10:00:00Z', 'tenant-1', 'local-1', 'ref-42', 'declaration', 'DECLARATION', 'file.xml', 'urssaf-1', 'REJECTED', $reports),
            'urssaf.pn.submission.rejected',
            $common + ['status' => 'REJECTED', 'reports' => $reports],
        ];
        yield 'payment status changed' => [
            new DeclarationPaymentStatusChanged('event-1', '2026-03-01T10:00:00Z', 'tenant-1', 'local-1', 'ref-42', 'declaration', 'DECLARATION', 'file.xml', 'urssaf-1', 'NOT_INITIATED', 'PROCESSED'),
            'urssaf.pn.declaration.payment_status_changed',
            $common + ['previousPaymentStatus' => 'NOT_INITIATED', 'paymentStatus' => 'PROCESSED'],
        ];
        yield 'identification without declaration type nor reference' => [
            new SubmissionOutcomeUnknown('event-2', '2026-03-01T10:00:00Z', 'tenant-1', 'local-2', 'ref-43', 'identification', null, 'file.xml.gz', null),
            'urssaf.pn.submission.outcome_unknown',
            [
                'eventId' => 'event-2',
                'occurredAt' => '2026-03-01T10:00:00Z',
                'tenantId' => 'tenant-1',
                'aggregateId' => 'local-2',
                'externalReference' => 'ref-43',
                'kind' => 'identification',
                'declarationType' => null,
                'fileName' => 'file.xml.gz',
                'urssafRef' => null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    #[DataProvider('events')]
    public function testAnEventExposesItsTypeVersionAndProperties(IntegrationEvent $event, string $type, array $properties): void
    {
        static::assertSame($type, $event->type());
        static::assertSame(1, $event->version());
        static::assertSame($properties, $event->toArray());
    }

    #[DataProvider('events')]
    public function testAnEventIsDecodedAsEncoded(IntegrationEvent $event): void
    {
        static::assertEquals($event, IntegrationEventCodec::decode(IntegrationEventCodec::encode($event)));
    }

    public function testEveryEventClassIsDecodable(): void
    {
        $types = [];
        foreach ([...self::events()] as [$event]) {
            static::assertContains($event::class, IntegrationEventCodec::CLASSES);
            $types[] = $event->type();
        }

        static::assertCount(\count(IntegrationEventCodec::CLASSES), array_unique($types));
    }

    public function testOnlyEncodedIntegrationEventsAreDecoded(): void
    {
        foreach ([serialize('text'), serialize(new stdClass())] as $data) {
            try {
                IntegrationEventCodec::decode($data);
                static::fail('The data should be refused.');
            } catch (InvalidArgumentException $failure) {
                static::assertSame('The data is not an encoded integration event.', $failure->getMessage());
            }
        }
    }

    public function testNoOtherClassIsInstantiatedWhileDecoding(): void
    {
        try {
            IntegrationEventCodec::decode(serialize(new WakeUpRecorder()));
        } catch (InvalidArgumentException $failure) {
            static::assertSame('The data is not an encoded integration event.', $failure->getMessage());
        }

        static::assertFalse(WakeUpRecorder::wokenUp());
    }

    private static function uploaded(): SubmissionUploaded
    {
        return new SubmissionUploaded('event-1', '2026-03-01T10:00:00Z', 'tenant-1', 'local-1', 'ref-42', 'declaration', 'DECLARATION', 'file.xml', 'urssaf-1');
    }
}
