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
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadDeclaration;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\UploadDeclarationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileName;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\File\LocalFileSource;

/**
 * Uploads a declaration or regularisation file (section 2.5) from the local file system under a host reference. Uploads
 * are serialised per tenant and declaration type (ADR 0008).
 */
#[AsCommand(name: 'urssaf:pn:declaration:upload', description: 'Upload a declaration or regularisation file to URSSAF')]
final class UploadDeclarationCommand extends Command
{
    private const ARGUMENT_FILE = 'file';
    private const OPTION_TYPE = 'type';
    private const OPTION_EXTERNAL_REFERENCE = 'external-reference';
    private const OPTION_NAME = 'name';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly UploadDeclarationHandler $upload,
        private readonly OperationLocks $locks,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_FILE, InputArgument::REQUIRED, 'Path of the file to upload');
        $this->addOption(self::OPTION_TYPE, null, InputOption::VALUE_REQUIRED, 'Type of the declaration: DECLARATION or REGULARISATION (required)');
        $this->addOption(self::OPTION_EXTERNAL_REFERENCE, null, InputOption::VALUE_REQUIRED, 'Your reference of the record (required)');
        $this->addOption(self::OPTION_NAME, null, InputOption::VALUE_REQUIRED, 'Name of the file sent to URSSAF (default: the base name of the path)');
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $type = DeclarationType::tryFrom(CommandInputs::requiredText($input, self::OPTION_TYPE))
                ?? throw new InvalidOptionException('The --type option must be "DECLARATION" or "REGULARISATION".');
            $reference = ExternalReference::fromString(CommandInputs::requiredText($input, self::OPTION_EXTERNAL_REFERENCE));
            $path = CommandInputs::requiredArgument($input, self::ARGUMENT_FILE);
            $name = CommandInputs::text($input, self::OPTION_NAME);
            $file = $name === null ? LocalFileSource::fromPath($path) : LocalFileSource::fromPathNamed($path, FileName::fromString($name));

            return $this->locks->exclusively(
                OperationLocks::DECLARATION_UPLOAD,
                $tenant->value() . '.' . $type->value,
                fn(): int => CommandReports::renderUpload($output, ($this->upload)(UploadDeclaration::of($tenant, $reference, $type, $file))),
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
