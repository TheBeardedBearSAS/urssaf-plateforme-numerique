<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissions;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ReconcileUnknownSubmissionsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\ReconcileReport;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\Submission;

/**
 * Looks for the uploads whose outcome is unknown (or stale) in the URSSAF searches (ADR 0003), tenant by tenant, each
 * tenant under its lock.
 */
#[AsCommand(name: 'urssaf:pn:submissions:reconcile', description: 'Reconcile the uploads whose outcome is unknown with URSSAF')]
final class ReconcileSubmissionsCommand extends Command
{
    /**
     * @param list<string> $configuredTenants tenants processed when --tenant is not given
     */
    public function __construct(
        private readonly ReconcileUnknownSubmissionsHandler $reconcile,
        private readonly SubmissionRepository $submissions,
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
                    $unresolved = $this->submissions->unresolved($tenant, $this->policy->pageSize($limit));
                    array_push($rows, ...array_map(static fn(Submission $submission): array => [$tenant->value(), $submission->id()->value()], $unresolved));
                }

                return CommandReports::renderPlan($output, $rows);
            }
            $report = ReconcileReport::of();
            foreach ($tenants as $tenant) {
                $report = $report->merge($this->locks->exclusively(
                    OperationLocks::RECONCILE_SUBMISSIONS,
                    $tenant->value(),
                    fn(): ReconcileReport => ($this->reconcile)(ReconcileUnknownSubmissions::forTenant($tenant, $limit)),
                    static function (string $resource) use ($output): ReconcileReport {
                        CommandReports::renderBusy($output, $resource);

                        return ReconcileReport::of();
                    },
                ));
            }

            return CommandReports::renderReport($output, $report);
        });
    }
}
