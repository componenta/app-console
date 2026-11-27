<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Closure;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean',
    description: 'Unpublish the current build while preserving generations used by running workers',
)]
final class CleanCommand extends Command
{
    /** @param Closure(): void $clean */
    public function __construct(private readonly Closure $clean)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ($this->clean)();

        (new SymfonyStyle($input, $output))->success('Application build unpublished. Existing generations are retained for running workers.');

        return Command::SUCCESS;
    }
}
