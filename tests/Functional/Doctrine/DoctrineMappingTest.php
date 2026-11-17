<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DecimalNumber;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportedPeriod;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\ReportFiles;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionError;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionSnapshot;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Shared\Persistence\SubmissionBuilder;

/**
 * Mapping scenarios (schema, round trips through the entity manager only, UTC instants): one method per scenario
 * (reference DoctrineMappingTest).
 *
 * @mago-expect lint:too-many-methods
 */
final class DoctrineMappingTest extends TestCase
{
    private const TABLE = 'urssaf_pn_submissions';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
    }

    public function testTheMappingIsValidAndInSyncWithTheSchema(): void
    {
        $validator = new SchemaValidator($this->entityManager);

        static::assertSame([], $validator->validateMapping());
        static::assertTrue($validator->schemaInSyncWithMetadata());
    }

    public function testTablesArePrefixedAndTheSubmissionConstraintsAndIndexesExist(): void
    {
        $manager = $this->entityManager->getConnection()->createSchemaManager();
        $tables = array_map(static fn(Table $table): string => $table->getObjectName()->getUnqualifiedName()->getValue(), $manager->introspectTables());

        static::assertSame(self::TABLE, $this->entityManager->getClassMetadata(Submission::class)->getTableName());
        static::assertContains(self::TABLE, $tables);
        foreach ($tables as $table) {
            static::assertStringStartsWith(DoctrineIntegration::TABLE_PREFIX, $table);
        }
        static::assertSame(
            [
                'idx_urssaf_pn_submission_declaration_slot' => [false, ['tenant_id', 'declaration_type', 'state']],
                'idx_urssaf_pn_submission_polling' => [false, ['tenant_id', 'state', 'last_polled_at']],
                'idx_urssaf_pn_submission_urssaf_ref' => [false, ['tenant_id', 'urssaf_ref']],
                'uniq_urssaf_pn_submission_external_reference' => [true, ['tenant_id', 'external_reference']],
            ],
            self::indexes($manager->introspectTableByUnquotedName(self::TABLE)),
        );
    }

    public function testTheSubmissionHasRealColumnsAndNoColumnForTheUploader(): void
    {
        $columns = [];
        foreach ($this->entityManager->getConnection()->createSchemaManager()->introspectTableByUnquotedName(self::TABLE)->getColumns() as $column) {
            $columns[] = $column->getObjectName()->getIdentifier()->getValue();
        }
        sort($columns);

        static::assertSame(
            [
                'attempts', 'created_at', 'declaration_type', 'errors', 'external_reference', 'file_name', 'fingerprint', 'id', 'kind',
                'last_polled_at', 'platform_id', 'raw_payment_status', 'raw_status', 'snapshot', 'state', 'tenant_id', 'updated_at',
                'urssaf_ref', 'version',
            ],
            $columns,
        );
        foreach ($columns as $column) {
            static::assertDoesNotMatchRegularExpression('/user|email|content/i', $column, $column);
        }
    }

    public function testTheVersionIsAnOptimisticLockColumnStartingAtOne(): void
    {
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        static::assertSame(1, $this->version());

        SubmissionBuilder::inProcess($submission, 'urssaf-1', '2026-03-01T00:01:00Z');
        $this->entityManager->flush();

        static::assertSame(2, $this->version());
        static::assertTrue($this->entityManager->getClassMetadata(Submission::class)->isVersioned);
    }

    public function testANeverPolledSubmissionIsReadBackIdenticallyWithoutSnapshot(): void
    {
        $submission = SubmissionBuilder::identification('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');

        $read = $this->roundTrip($submission);

        $submission->releaseEvents();
        static::assertEquals($submission, $read);
        static::assertNull($read->snapshot());
        static::assertNull($read->identity()->declarationType());
        static::assertSame([], $read->errors());
    }

    public function testThePolledSubmissionIsReadBackIdenticallyWithItsCompleteIdentity(): void
    {
        $submission = SubmissionBuilder::inProcess(
            SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z', DeclarationType::Regularisation),
            'urssaf-1',
            '2026-03-01T00:01:00Z',
        );
        $submission->applySnapshot(
            new SubmissionSnapshot(
                'ACCEPTED',
                'PROCESSING',
                [ReportedPeriod::fromWire('2026-02')],
                ReportFiles::of(FileReference::of('file-1', 'report.csv'), null, null, null),
                ['numberOfError' => DecimalNumber::fromInt(0)],
                Instant::fromIso8601('2026-03-01T00:02:00Z'),
            ),
            Instant::fromIso8601('2026-03-01T00:03:00Z'),
        );

        $read = $this->roundTrip($submission);

        $submission->releaseEvents();
        static::assertNotSame($submission, $read);
        static::assertEquals($submission, $read);
        static::assertSame([], $read->releaseEvents());
        static::assertTrue($read->identity()->id()->equals($read->id()));
        static::assertSame('s-1', $read->identity()->id()->value());
        static::assertSame(DeclarationType::Regularisation, $read->identity()->declarationType());
        static::assertEquals($submission->snapshot(), $read->snapshot());
        static::assertSame(
            ['raw_status' => 'ACCEPTED', 'raw_payment_status' => 'PROCESSING', 'state' => 'accepted', 'kind' => 'declaration', 'declaration_type' => 'REGULARISATION'],
            $this->entityManager->getConnection()->fetchAssociative('SELECT raw_status, raw_payment_status, state, kind, declaration_type FROM ' . self::TABLE),
        );
    }

    public function testTheErrorsOfARefusedUploadAreReadBack(): void
    {
        $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z');
        $submission->markUploadRejected([SubmissionError::of('ERR_FORMAT', 'Invalid file.')], Instant::fromIso8601('2026-03-01T00:01:00Z'));

        $read = $this->roundTrip($submission);

        static::assertEquals($submission->errors(), $read->errors());
        static::assertSame('[{"code":"ERR_FORMAT","message":"Invalid file."}]', $this->entityManager->getConnection()->fetchOne('SELECT errors FROM ' . self::TABLE));
    }

    public function testAbsentValuesAreStoredAsSqlNull(): void
    {
        $this->entityManager->persist(SubmissionBuilder::identification('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));
        $this->entityManager->flush();

        static::assertSame(
            ['declaration_type' => null, 'urssaf_ref' => null, 'raw_status' => null, 'raw_payment_status' => null, 'snapshot' => null, 'last_polled_at' => null, 'errors' => '[]'],
            $this->entityManager->getConnection()->fetchAssociative(
                'SELECT declaration_type, urssaf_ref, raw_status, raw_payment_status, snapshot, last_polled_at, errors FROM ' . self::TABLE,
            ),
        );
    }

    public function testInstantsAreStoredInUtcWhateverTheDefaultTimeZone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            $submission = SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T23:30:00+01:00');
            $read = $this->roundTrip($submission);
            $stored = $this->entityManager->getConnection()->fetchAssociative('SELECT created_at, updated_at FROM ' . self::TABLE);
        } finally {
            date_default_timezone_set($previous);
        }

        $submission->releaseEvents();
        static::assertEquals($submission, $read);
        static::assertSame(['created_at' => '2026-03-01 22:30:00', 'updated_at' => '2026-03-01 22:30:00'], $stored);
    }

    public function testTheUniqueConstraintIsEnforcedByTheSchema(): void
    {
        $this->entityManager->persist(SubmissionBuilder::declaration('s-1', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));
        $this->entityManager->flush();
        $this->entityManager->persist(SubmissionBuilder::declaration('s-2', 'tenant-1', 'host-1', '2026-03-01T00:00:00Z'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    private function version(): int
    {
        $version = $this->entityManager->getConnection()->fetchOne('SELECT version FROM ' . self::TABLE);
        static::assertIsNumeric($version);

        return (int) $version;
    }

    private function roundTrip(Submission $submission): Submission
    {
        $this->entityManager->persist($submission);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->entityManager->find(Submission::class, $submission->id());
        static::assertInstanceOf(Submission::class, $read);

        return $read;
    }

    /**
     * @return array<string, array{bool, list<string>}> unique flag and columns by index name
     */
    private static function indexes(Table $table): array
    {
        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            $name = $index->getObjectName()->getIdentifier()->getValue();
            if (str_starts_with($name, 'idx_') || str_starts_with($name, 'uniq_')) {
                $indexes[$name] = [
                    $index->getType() === IndexType::UNIQUE,
                    array_map(static fn(IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(), $index->getIndexedColumns()),
                ];
            }
        }
        ksort($indexes);

        return $indexes;
    }
}
