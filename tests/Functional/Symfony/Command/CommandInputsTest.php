<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidOptionException;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CommandInputs;

final class CommandInputsTest extends TestCase
{
    public function testAnOptionIsATextOrNull(): void
    {
        static::assertNull(CommandInputs::text(TestInput::of([]), 'name'));
        static::assertSame('abc', CommandInputs::text(TestInput::of(['--name' => 'abc']), 'name'));
    }

    public function testARequiredTextIsNamed(): void
    {
        static::assertSame('abc', CommandInputs::requiredText(TestInput::of(['--name' => 'abc']), 'name'));
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --name option is required.');

        CommandInputs::requiredText(TestInput::of([]), 'name');
    }

    public function testARequiredArgumentIsNamed(): void
    {
        static::assertSame('abc', CommandInputs::requiredArgument(TestInput::of(['id' => 'abc']), 'id'));
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The "id" argument is required.');

        CommandInputs::requiredArgument(TestInput::of([]), 'id');
    }

    public function testAPositiveIntegerNamesItsOption(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --page option must be a positive integer.');

        CommandInputs::positiveInteger(TestInput::of(['--page' => '0']), 'page');
    }

    public function testThePositiveIntegerOfAnOptionIsNullWhenAbsent(): void
    {
        static::assertNull(CommandInputs::positiveInteger(TestInput::of([]), 'page'));
        static::assertSame(3, CommandInputs::positiveInteger(TestInput::of(['--page' => '3']), 'page'));
    }
}
