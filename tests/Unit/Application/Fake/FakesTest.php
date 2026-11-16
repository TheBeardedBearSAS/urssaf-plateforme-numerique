<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use DateTimeZone;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\BinaryStream;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafPlateformeNumerique\Contract\Event\SubmissionOutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\PageInfo;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\InMemoryFileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\DeclarationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\IdentificationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

/**
 * The shared fakes behave as their callers expect: the scripted gateway, the harness and the recorders.
 *
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class FakesTest extends TestCase
{
    public function testTheGatewayAnswersTheScriptedUploadsInOrderAndJournalsThem(): void
    {
        $journal = new Journal();
        $failure = new RuntimeException('down');
        $gateway = (new FakePlatformGateway($journal))
            ->willUploadIdentification(SubmissionSamples::urssafReference(1), $failure)
            ->willUploadIdentification(SubmissionSamples::urssafReference(2))
            ->willUploadDeclaration(SubmissionSamples::urssafReference(3));
        $file = InMemoryFileSource::of(SubmissionSamples::fileName(1), 'content');

        $first = $gateway->uploadIdentification(Harness::tenant(), Harness::platform(), $file);
        try {
            $gateway->uploadIdentification(Harness::tenant(), Harness::platform(), $file);
            static::fail('The scripted failure must be thrown.');
        } catch (RuntimeException $thrown) {
            static::assertSame($failure, $thrown);
        }
        $third = $gateway->uploadIdentification(Harness::tenant(Harness::OTHER_TENANT), Harness::platform(Harness::OTHER_PLATFORM), $file);
        $declaration = $gateway->uploadDeclaration(Harness::tenant(), Harness::platform(), DeclarationType::Regularisation, $file);

        static::assertSame('urssaf-1', $first->value());
        static::assertSame('urssaf-2', $third->value());
        static::assertSame('urssaf-3', $declaration->value());
        $expected = [
            'upload_identification:file-1.zip',
            'upload_identification:file-1.zip',
            'upload_identification:file-1.zip',
            'upload_declaration:REGULARISATION:file-1.zip',
        ];
        static::assertSame($expected, $gateway->recorded()->calls());
        static::assertSame($expected, $journal->entries());
        static::assertSame(
            ['tenant-1|platform-1', 'tenant-1|platform-1', 'tenant-2|platform-2', 'tenant-1|platform-1'],
            $gateway->recorded()->targets(),
        );
    }

    public function testTheGatewayRefusesAnUnscriptedCall(): void
    {
        $gateway = new FakePlatformGateway();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unexpected uploadIdentification() call.');

        $gateway->uploadIdentification(Harness::tenant(), Harness::platform(), InMemoryFileSource::of(SubmissionSamples::fileName(1), 'x'));
    }

    public function testTheGatewayRefusesAnExhaustedScript(): void
    {
        $gateway = (new FakePlatformGateway())->willUploadDeclaration(SubmissionSamples::urssafReference(1));
        $file = InMemoryFileSource::of(SubmissionSamples::fileName(1), 'x');
        $gateway->uploadDeclaration(Harness::tenant(), Harness::platform(), DeclarationType::Declaration, $file);

        $this->expectException(LogicException::class);

        $gateway->uploadDeclaration(Harness::tenant(), Harness::platform(), DeclarationType::Declaration, $file);
    }

    public function testTheGatewayReadsSearchesAndDownloads(): void
    {
        $download = new DownloadedFile(self::stream(), 'application/pdf', null);
        $gateway = (new FakePlatformGateway())
            ->willSearchIdentifications(Page::of([self::identification()], PageInfo::of(1, 1, 1, 1)))
            ->willSearchDeclarations(Page::of([self::declaration()], PageInfo::of(1, 1, 1, 1)))
            ->willDownloadIdentificationFile($download)
            ->willDownloadDeclarationFile($download)
            ->willReadNewsFeed(Page::of([NewsItem::of('news-1', null, null)], PageInfo::of(1, 1, 1, 1)));
        $reference = SubmissionSamples::urssafReference(1);
        $file = FileReference::of('file-global', null);
        $search = SubmissionSearch::all();

        static::assertCount(1, $gateway->searchIdentifications(Harness::tenant(), Harness::platform(), $search));
        static::assertCount(1, $gateway->searchDeclarations(Harness::tenant(), Harness::platform(), $search));
        static::assertSame($download, $gateway->downloadIdentificationFile(Harness::tenant(), Harness::platform(), $reference, $file));
        static::assertSame($download, $gateway->downloadDeclarationFile(Harness::tenant(), Harness::platform(), $reference, $file));
        static::assertCount(1, $gateway->newsFeed(Harness::tenant(), Harness::platform(), PageRequest::firstPage()));
        static::assertSame(
            [
                'search_identifications',
                'search_declarations',
                'download_identification:urssaf-1:file-global',
                'download_declaration:urssaf-1:file-global',
                'news_feed',
            ],
            $gateway->recorded()->calls(),
        );
        static::assertSame([$search, $search], $gateway->recorded()->searches());
        static::assertCount(1, $gateway->recorded()->newsFeedRequests());
    }

    public function testTheGatewayGetsThrowTheScriptedFailure(): void
    {
        $failure = new RuntimeException('gone');
        $gateway = (new FakePlatformGateway())->willGetIdentification($failure)->willGetDeclaration($failure);

        try {
            $gateway->getIdentification(Harness::tenant(), Harness::platform(), SubmissionSamples::urssafReference(1));
            static::fail('The scripted failure must be thrown.');
        } catch (RuntimeException $thrown) {
            static::assertSame($failure, $thrown);
        }
        try {
            $gateway->getDeclaration(Harness::tenant(), Harness::platform(), SubmissionSamples::urssafReference(1));
            static::fail('The scripted failure must be thrown.');
        } catch (RuntimeException $thrown) {
            static::assertSame($failure, $thrown);
        }

        static::assertSame(['get_identification:urssaf-1', 'get_declaration:urssaf-1'], $gateway->recorded()->calls());
    }

    public function testTheHarnessBuildsEverySubmissionState(): void
    {
        $harness = new Harness();

        foreach (SubmissionState::cases() as $index => $state) {
            $submission = $harness->identification($index + 1, $state);

            static::assertSame($state, $submission->state());
            static::assertSame([], $submission->releaseEvents());
        }
        $declaration = $harness->declaration(20, SubmissionState::InProcess, DeclarationType::Regularisation, Harness::OTHER_TENANT);

        static::assertCount(\count(SubmissionState::cases()) + 1, $harness->submissions->all());
        static::assertSame('platform-2', $declaration->identity()->platformId()->value());
        static::assertSame('urssaf-20', $declaration->urssafReference()?->value());
        static::assertSame('platform-1', $harness->platforms->forTenant(Harness::tenant())->value());
        static::assertSame('2026-01-15T10:00:00Z', $harness->clock->now()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testTheHarnessUnitOfWorkRecordsPublishesAndAppends(): void
    {
        $harness = new Harness();
        $event = DomainEventSamples::uploaded();

        $harness->unitOfWork->run(static function (RecordedEvents $events) use ($event): void {
            $events->record($event);
        });

        static::assertSame([$event], $harness->events->events());
        static::assertSame(['SubmissionUploaded'], $harness->events->names());
        static::assertCount(1, $harness->outbox->pending(10));
        static::assertSame(1, $harness->transactions->committed());
        static::assertSame(['begin', 'commit', 'publish:SubmissionUploaded'], $harness->journal->entries());
    }

    public function testTheJournalCanBeCleared(): void
    {
        $journal = new Journal();
        $journal->add('a');
        $journal->clear();
        $journal->add('b');

        static::assertSame(['b'], $journal->entries());
    }

    public function testTheClockCanBeMoved(): void
    {
        $clock = new FixedClock('2026-01-15T10:00:00Z');
        $clock->moveTo('2026-01-16T10:00:00Z');

        static::assertSame('2026-01-16', $clock->now()->format('Y-m-d'));
    }

    public function testTheSinkRecordsAndRefuses(): void
    {
        $sink = new RecordingIntegrationEventSink(['sub-2']);
        $accepted = new SubmissionOutcomeUnknown('e1', '2026-01-15T10:00:00Z', 'tenant-1', 'sub-1', 'ref-1', 'identification', null, 'f.zip', null);
        $refused = new SubmissionOutcomeUnknown('e2', '2026-01-15T10:00:00Z', 'tenant-1', 'sub-2', 'ref-2', 'identification', null, 'f.zip', null);

        $sink->dispatch($accepted);
        try {
            $sink->dispatch($refused);
            static::fail('The refused record must fail.');
        } catch (RuntimeException $failure) {
            static::assertSame('Broker down for sub-2.', $failure->getMessage());
        }

        static::assertSame(['e1'], $sink->deliveredIds());
    }

    public function testTheStatisticsAndTheIdGenerator(): void
    {
        $ids = new SequentialIdGenerator('x-');

        static::assertSame([], (new FixedWorkflowStatistics([]))->submissionStates());
        static::assertSame(['x-1', 'x-2'], [$ids->generate(), $ids->generate()]);
    }

    private static function identification(): IdentificationSubmission
    {
        return IdentificationMapper::map(WireFixtures::fixture('identification_response_handbuilt'), new DateTimeZone('UTC'));
    }

    private static function declaration(): DeclarationSubmission
    {
        return DeclarationMapper::map(WireFixtures::fixture('declaration_response_handbuilt'), new DateTimeZone('UTC'));
    }

    private static function stream(): BinaryStream
    {
        return new class implements BinaryStream {
            public function read(int $length): string
            {
                return '';
            }

            public function eof(): bool
            {
                return true;
            }

            public function copyTo(mixed $resource): int
            {
                return 0;
            }

            public function close(): void {}
        };
    }
}
