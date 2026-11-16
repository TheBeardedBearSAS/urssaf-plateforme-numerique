<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\FailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\DomainEvent;
use Throwable;

final class RecordingFailureReporter implements FailureReporter
{
    /** @var list<array{Throwable, list<DomainEvent>}> */
    private array $reports = [];

    public function eventPublicationFailed(Throwable $failure, array $events): void
    {
        $this->reports[] = [$failure, $events];
    }

    /**
     * @return list<array{Throwable, list<DomainEvent>}>
     */
    public function reports(): array
    {
        return $this->reports;
    }
}
