<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatuses;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\SyncSubmissionStatusesHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\SyncReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;

/**
 * Polls the submissions whose URSSAF status may still change (ADR 0004), tenant by tenant, each tenant under its lock.
 */
#[AsCommand(name: 'urssaf:pn:submissions:sync', description: 'Poll the status of the submissions in process at URSSAF')]
final class SyncSubmissionsCommand extends Command
{
    /**
     * @param list<string> $configuredTenants tenants processed when --tenant is not given
     */
    public function __construct(
        private readonly SyncSubmissionStatusesHandler $sync,
        private readonly SubmissionRepository $submissions,
        private readonly Clock $clock,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the configured tenants)');
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $limit = CommandOptions::limit($input);
            $tenants = CommandTenants::tenantsOrConfigured($input, $this->configuredTenants);
            if (CommandOptions::dryRun($input)) {
                $rows = [];
                foreach ($tenants as $tenant) {
                    $due = $this->submissions->dueForPolling(
                        $tenant,
                        Instant::fromDateTime($this->clock->now()),
                        $this->policy->pollingWindow(),
                        $this->policy->pageSize($limit),
                    );
                    array_push($rows, ...array_map(static fn(Submission $submission): array => [$tenant->value(), $submission->id()->value()], $due));
                }

                return CommandReports::renderPlan($output, $rows);
            }
            $report = SyncReport::of();
            foreach ($tenants as $tenant) {
                $report = $report->merge($this->locks->exclusively(
                    OperationLocks::SYNC_SUBMISSIONS,
                    $tenant->value(),
                    fn(): SyncReport => ($this->sync)(SyncSubmissionStatuses::forTenant($tenant, $limit)),
                    static function (string $resource) use ($output): SyncReport {
                        CommandReports::renderBusy($output, $resource);

                        return SyncReport::of();
                    },
                ));
            }

            return CommandReports::renderReport($output, $report);
        });
    }
}
