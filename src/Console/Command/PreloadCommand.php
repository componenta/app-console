<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Componenta\App\Cache\AtomicFile;
use Componenta\App\Cache\CacheLayout;
use Componenta\Config\Config;
use Componenta\Stdlib\PathResolverInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:preload',
    description: 'Generate preload file for built application cache',
)]
final class PreloadCommand extends Command
{
    public function __construct(
        private readonly Config $config,
        private readonly PathResolverInterface $paths,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cache = CacheLayout::fromConfig($this->config, $this->paths);
        $files = [
            $cache->config,
            $cache->container,
            $cache->containerFactory,
            $cache->routes,
            $cache->diPlans,
            $cache->discovery,
            $cache->policies,
            $cache->interceptors,
        ];

        $requires = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $requires[] = sprintf("require __DIR__ . '/%s';", basename($file));
        }

        AtomicFile::replace(
            $cache->preload,
            "<?php\n\ndeclare(strict_types=1);\n\n" . implode("\n", $requires) . "\n",
            'preload file',
        );

        $io->success(sprintf('Preload file: %s', $cache->preload));

        return Command::SUCCESS;
    }
}
