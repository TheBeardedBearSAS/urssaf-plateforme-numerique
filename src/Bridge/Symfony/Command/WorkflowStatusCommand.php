<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\TenantOverview;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionState;

/**
 * Prints the submissions by tenant and state (table, or JSON for monitoring). Reads the local database only.
 */
#[AsCommand(name: 'urssaf:pn:status', description: 'Counts the submissions by tenant and state')]
final class WorkflowStatusCommand extends Command
{
    public function __construct(private readonly GetWorkflowOverviewHandler $overview)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: every tenant with submissions)');
        CommandOptions::addFormat($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $format = CommandOptions::format($input);
            $tenant = CommandTenants::tenant($input);
            $tenants = ($this->overview)($tenant instanceof TenantId ? GetWorkflowOverview::forTenant($tenant) : GetWorkflowOverview::allTenants())->tenants();
            if ($format === CommandOptions::FORMAT_JSON) {
                $output->writeln(json_encode(array_map(static fn(TenantOverview $overview): array => $overview->toArray(), $tenants), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }
            $table = new Table($output);
            $table->setHeaders(['Tenant', ...array_map(static fn(SubmissionState $state): string => $state->value, SubmissionState::cases()), 'Needing attention']);
            $table->setRows(array_map(
                static fn(TenantOverview $overview): array => [
                    $overview->tenantId,
                    ...array_map(static fn(SubmissionState $state): int => $overview->states[$state->value], SubmissionState::cases()),
                    $overview->needingAttention(),
                ],
                $tenants,
            ));
            $table->render();

            return Command::SUCCESS;
        });
    }
}
