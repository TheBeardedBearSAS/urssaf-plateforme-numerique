<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Output\BufferedOutput;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\CommandReports;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;

final class CommandReportsTest extends TestCase
{
    public function testTheGuardGivesTheStatusOfTheAction(): void
    {
        static::assertSame(0, CommandReports::guard(new BufferedOutput(), static fn(): int => Command::SUCCESS));
        static::assertSame(1, CommandReports::guard(new BufferedOutput(), static fn(): int => Command::FAILURE));
    }

    public function testAnInvalidOptionIsAnInvalidUsage(): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static fn(): int => throw new InvalidOptionException('The --limit option must be a positive integer.'));

        static::assertSame(2, $status);
        static::assertSame("The --limit option must be a positive integer.\n", $output->fetch());
    }

    public function testAValueRefusedByTheDomainIsAnInvalidUsage(): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static fn(): int => throw InvalidValue::empty('tenant_id'));

        static::assertSame(2, $status);
        static::assertSame("Invalid tenant_id: the value must not be empty. (tenant_id.empty)\n", $output->fetch());
    }

    public function testEveryViolationOfAFailedValidationIsPrinted(): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static function (): int {
            PageRequest::of(0, 0);

            return Command::SUCCESS;
        });

        static::assertSame(2, $status);
        static::assertSame(
            "page: The page number must be at least 1. (page_request.page_not_positive)\n"
            . "size: The page size must be between 1 and 500. (page_request.size_out_of_range)\n"
            . "Invalid input: 2 error(s).\n",
            $output->fetch(),
        );
    }

    public function testAnotherPackageFailureIsAFailure(): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static fn(): int => throw OperationNotSupported::newsFeedOnTestApi());

        static::assertSame(1, $status);
        static::assertSame("The news feed (getPlatformNewsFeed) is not available on the test API.\n", $output->fetch());
    }

    public function testABusyOperationIsNotAFailure(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::renderBusy($output, 'urssaf_pn.submissions.sync.tenant-1'));

        static::assertSame("Already running (lock \"urssaf_pn.submissions.sync.tenant-1\" is held): skipped.\n", $output->fetch());
    }

    public function testAPlanListsTheSubmissionsAndSaysNothingWasCalled(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::renderPlan($output, [['tenant-1', 'submission-1'], ['tenant-2', 'submission-2']]));

        $display = $output->fetch();
        static::assertSame([['Tenant', 'Submission'], ['tenant-1', 'submission-1'], ['tenant-2', 'submission-2']], Output::rows($display));
        static::assertStringEndsWith("Dry run: 2 submission(s) would be processed, URSSAF was not called.\n", $display);
    }
}
