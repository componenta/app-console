<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Componenta\App\Cache\CacheLayout;
use Componenta\Config\Config;
use Componenta\Stdlib\PathResolverInterface;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cache:clear',
    description: 'Clear application build, development, and runtime cache directories',
)]
final class CacheClearCommand extends Command
{
    public function __construct(
        private readonly Config $config,
        private readonly PathResolverInterface $paths,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('build', null, InputOption::VALUE_NONE, 'Clear build cache only')
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Clear development cache only')
            ->addOption('runtime', null, InputOption::VALUE_NONE, 'Clear runtime cache only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cache = CacheLayout::fromConfig($this->config, $this->paths);
        $selected = [
            'build' => (bool) $input->getOption('build'),
            'dev' => (bool) $input->getOption('dev'),
            'runtime' => (bool) $input->getOption('runtime'),
        ];

        if (!in_array(true, $selected, true)) {
            $selected = array_fill_keys(array_keys($selected), true);
        }

        $cleared = [];

        if ($selected['build']) {
            $cleared[] = sprintf('build: %d item(s)', $this->clearDirectory($cache->buildDir));
        }

        if ($selected['dev']) {
            $cleared[] = sprintf('dev: %d item(s)', $this->clearDirectory($cache->devDir));
        }

        if ($selected['runtime']) {
            $cleared[] = sprintf('runtime: %d item(s)', $this->clearDirectory($cache->runtimeDir));
        }

        $io->success($cleared);

        return Command::SUCCESS;
    }

    private function clearDirectory(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $real = realpath($directory);

        if ($real === false || strlen($real) < 4) {
            throw new RuntimeException(sprintf('Refusing to clear unsafe cache directory: %s', $directory));
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isDir()) {
                if (!rmdir($path)) {
                    throw new RuntimeException(sprintf('Unable to remove cache directory: %s', $path));
                }
            } elseif (!unlink($path)) {
                throw new RuntimeException(sprintf('Unable to remove cache file: %s', $path));
            }

            $count++;
        }

        return $count;
    }
}
