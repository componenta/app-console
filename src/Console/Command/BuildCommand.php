<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Closure;
use Componenta\App\Build\ApplicationBuildOrchestrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:build',
    description: 'Run registered application builders',
)]
final class BuildCommand extends Command
{
    /** @param Closure(): ApplicationBuildOrchestrator $orchestrator */
    public function __construct(
        private readonly Closure $orchestrator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ($this->orchestrator)()->build();

        (new SymfonyStyle($input, $output))->success('Application build completed.');

        return Command::SUCCESS;
    }
}