<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ResolveSubmissionOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\ResolveSubmissionOutcomeHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Settles an upload left in "outcome_unknown" after the operator checked the outcome with URSSAF (ADR 0003): either
 * URSSAF holds the file (give its urssafRef), or it never arrived.
 */
#[AsCommand(name: 'urssaf:pn:submission:resolve', description: 'Settle an outcome_unknown upload after a manual check (ADR 0003)')]
final class ResolveSubmissionCommand extends Command
{
    private const ARGUMENT_ID = 'id';
    private const OPTION_URSSAF_REF = 'urssaf-ref';
    private const OPTION_NOT_RECEIVED = 'not-received';

    public function __construct(private readonly ResolveSubmissionOutcomeHandler $resolve)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_ID, InputArgument::REQUIRED, 'Id of the submission');
        CommandOptions::addTenant($this, 'Tenant id (required)');
        $this->addOption(self::OPTION_URSSAF_REF, null, InputOption::VALUE_REQUIRED, 'The urssafRef URSSAF holds the file under');
        $this->addOption(self::OPTION_NOT_RECEIVED, null, InputOption::VALUE_NONE, 'URSSAF never received the file');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::requiredTenant($input);
            $id = SubmissionId::fromString(CommandInputs::requiredArgument($input, self::ARGUMENT_ID));
            $reference = CommandInputs::text($input, self::OPTION_URSSAF_REF);
            $notReceived = $input->getOption(self::OPTION_NOT_RECEIVED) === true;
            $hasReference = $reference !== null;
            if ($hasReference === $notReceived) {
                throw new InvalidOptionException('Give one of --urssaf-ref or --not-received.');
            }

            $view = ($this->resolve)($reference === null
                ? ResolveSubmissionOutcome::notReceived($tenant, $id)
                : ResolveSubmissionOutcome::received($tenant, $id, UrssafReference::fromString($reference)));
            CommandReports::renderFields($output, CommandViews::rows($view));

            return Command::SUCCESS;
        });
    }
}
