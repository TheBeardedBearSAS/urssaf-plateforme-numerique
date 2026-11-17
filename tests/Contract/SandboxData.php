<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FileSource;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\InMemoryFileSource;

/**
 * Fictitious, unique-per-run sandbox data: the file name is generated per run so the search can tell it apart.
 */
final class SandboxData
{
    /**
     * A fictitious identification file: a compressed XML placeholder, not a valid DPID document. URSSAF is expected
     * to reject its processing asynchronously.
     */
    public static function identificationFile(): FileSource
    {
        $name = \sprintf(
            'DPID_0000000_%s_%s.xml.gz',
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m'),
            strtoupper(bin2hex(random_bytes(4))),
        );
        $contents = gzencode('<?xml version="1.0" encoding="UTF-8"?><fictitious-contract-test/>');
        \assert(\is_string($contents), 'gzencode() succeeds on a short string.');

        return InMemoryFileSource::of(FileName::fromString($name), $contents);
    }
}
