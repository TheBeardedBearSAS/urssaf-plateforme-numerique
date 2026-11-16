<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeedHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;

/**
 * Prints a page of the news feed of the platform of a tenant (live call, not available on the test API).
 */
#[AsCommand(name: 'urssaf:pn:news', description: 'Show the news feed of the URSSAF platform')]
final class NewsFeedCommand extends Command
{
    private const OPTION_PAGE = 'page';
    private const OPTION_SIZE = 'size';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(private readonly GetNewsFeedHandler $news, private readonly array $configuredTenants)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        $this->addOption(self::OPTION_PAGE, null, InputOption::VALUE_REQUIRED, 'Page number, from 1');
        $this->addOption(self::OPTION_SIZE, null, InputOption::VALUE_REQUIRED, 'Page size, from 1 to ' . PageRequest::MAX_SIZE);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $page = ($this->news)(new GetNewsFeed($tenant, PageRequest::of(
                CommandInputs::positiveInteger($input, self::OPTION_PAGE),
                CommandInputs::positiveInteger($input, self::OPTION_SIZE),
            )));
            $table = new Table($output);
            $table->setHeaders(['Id', 'Date', 'Text']);
            $table->setRows(array_map(
                static fn(NewsItem $item): array => [$item->idNews() ?? '-', $item->newsDate()?->format() ?? '-', $item->newsText() ?? '-'],
                $page->items(),
            ));
            $table->render();
            $output->writeln(\sprintf('%d news item(s).', \count($page)));

            return Command::SUCCESS;
        });
    }
}
