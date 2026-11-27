<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Closure;
use Componenta\App\Build\PreparedBuild;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:build',
    description: 'Prepare and publish application build artifacts from current source',
)]
final class BuildCommand extends Command
{
    /** @param Closure(): PreparedBuild $build */
    public function __construct(
        private readonly Closure $build,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prepared = ($this->build)();

        $io = new SymfonyStyle($input, $output);
        foreach ($prepared->skipped() as $artifact => $reason) {
            $io->note(sprintf('%s uses source: %s', $artifact, $reason));
        }
        $io->success(sprintf('Application build completed. Generation: %s', $prepared->generation));

        return Command::SUCCESS;
    }
}
