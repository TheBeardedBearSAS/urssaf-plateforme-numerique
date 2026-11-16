<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\SubmissionId;

/**
 * Prints a local submission (no call to URSSAF): its state, attempts, URSSAF tracking and error codes.
 */
#[AsCommand(name: 'urssaf:pn:submission:show', description: 'Show a local submission (no call to URSSAF)')]
final class ShowSubmissionCommand extends Command
{
    private const ARGUMENT_ID = 'id';

    public function __construct(private readonly GetSubmissionHandler $get)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_ID, InputArgument::REQUIRED, 'Id of the submission');
        CommandOptions::addTenant($this, 'Tenant id (required)');
        CommandOptions::addFormat($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $format = CommandOptions::format($input);
            $tenant = CommandTenants::requiredTenant($input);
            $view = ($this->get)(new GetSubmission($tenant, SubmissionId::fromString(CommandInputs::requiredArgument($input, self::ARGUMENT_ID))));
            if ($format === CommandOptions::FORMAT_JSON) {
                $output->writeln(json_encode(CommandViews::data($view), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }
            CommandReports::renderFields($output, CommandViews::rows($view));

            return Command::SUCCESS;
        });
    }
}
