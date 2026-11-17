<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Port;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;

final class OperationTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation, string, bool}>
     */
    public static function provideOperations(): iterable
    {
        yield 'upload identification' => [Operation::UploadIdentification, 'uploadPlatformIdentification', true];
        yield 'get identification' => [Operation::GetIdentification, 'getPlatformIdentification', false];
        yield 'search identifications' => [Operation::SearchIdentifications, 'searchPlatformIdentification', false];
        yield 'download identification file' => [Operation::DownloadIdentificationFile, 'downloadIdentificationRelatedFile', false];
        yield 'upload declaration' => [Operation::UploadDeclaration, 'uploadPlatformDeclaration', true];
        yield 'get declaration' => [Operation::GetDeclaration, 'getPlatformDeclaration', false];
        yield 'search declarations' => [Operation::SearchDeclarations, 'searchPlatformDeclaration', false];
        yield 'download declaration file' => [Operation::DownloadDeclarationFile, 'downloadDeclarationRelatedFile', false];
        yield 'news feed' => [Operation::GetNewsFeed, 'getPlatformNewsFeed', false];
    }

    #[DataProvider('provideOperations')]
    public function testOperationIdAndUploadFlag(Operation $operation, string $operationId, bool $isUpload): void
    {
        static::assertSame($operationId, $operation->value);
        static::assertSame($operation, Operation::from($operationId));
        static::assertSame($isUpload, $operation->isUpload());
    }

    public function testEveryOperationIsCovered(): void
    {
        static::assertCount(\count(Operation::cases()), [...self::provideOperations()]);
    }
}
